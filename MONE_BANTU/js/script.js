// ============================================================
//  MONË BANTU — script.js global
// ============================================================

// Détection automatique du chemin vers les APIs PHP
// Fonctionne que la page soit à la racine (index.php) ou dans php/
const PHP  = window.location.pathname.includes('/php/') ? '' : 'php/';
const ROOT = window.location.pathname.includes('/php/') ? '../' : '';

document.addEventListener('DOMContentLoaded', function () {

    // ---- Menu hamburger ----
    const hamburger = document.querySelector('.hamburger');
    const navMenu   = document.querySelector('.nav-menu');
    if (hamburger && navMenu) {
        hamburger.addEventListener('click', function () {
            navMenu.classList.toggle('active');
            hamburger.classList.toggle('active');
        });
        document.addEventListener('click', function (e) {
            if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('active');
                hamburger.classList.remove('active');
            }
        });
    }

    // ---- Navbar scroll ----
    window.addEventListener('scroll', function () {
        const navbar = document.querySelector('.navbar');
        if (navbar) navbar.classList.toggle('scrolled', window.scrollY > 80);
    });

    // ---- Recherche live ----
    const searchInput = document.querySelector('.search-bar input[name="search"]');
    if (searchInput) {
        let searchTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            if (q.length < 3) { hideSuggestions(); return; }
            searchTimer = setTimeout(() => fetchSuggestions(q), 350);
        });
        searchInput.addEventListener('blur', () => setTimeout(hideSuggestions, 200));
    }

    // ---- Badge messages (toutes les 30s) ----
    checkMessagesBadge();
    setInterval(checkMessagesBadge, 30000);

    // ---- Animations scroll ----
    initScrollAnimation();

    // ---- Animations boutons (déléguées, valables sur toute page) ----
    initButtonAnimations();

    // ---- Réels (si un conteneur .reels-bar est présent sur la page) ----
    chargerReels();
    initReelsEvenements();

    // ---- Localisation silencieuse (si les champs cachés loc_* sont présents) ----
    rafraichirLocalisationSilencieuse();

});

// ============================================================
//  LOCALISATION — rafraîchissement silencieux à chaque publication
//  (remplit des champs cachés loc_region / loc_departement / loc_arrondissement /
//  loc_ville / loc_latitude / loc_longitude, s'ils existent sur la page)
// ============================================================
function rafraichirLocalisationSilencieuse() {
    if (!document.getElementById('loc_latitude') || !navigator.geolocation) return;

    navigator.geolocation.getCurrentPosition(function (pos) {
        const lat = pos.coords.latitude, lon = pos.coords.longitude;
        document.getElementById('loc_latitude').value  = lat;
        document.getElementById('loc_longitude').value = lon;

        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&addressdetails=1&accept-language=fr`)
            .then(r => r.json())
            .then(data => {
                const a = data.address || {};
                if (a.state) document.getElementById('loc_region').value = a.state;
                if (a.state_district || a.county) document.getElementById('loc_departement').value = a.state_district || a.county;
                if (a.county || a.municipality || a.suburb) document.getElementById('loc_arrondissement').value = a.county || a.municipality || a.suburb;
                if (a.city || a.town || a.village) document.getElementById('loc_ville').value = a.city || a.town || a.village;
            })
            .catch(() => {});
    }, function () { /* silencieux : pas de GPS, on garde la dernière localisation connue */ }, { timeout: 8000 });
}

// ============================================================
//  RÉELS
// ============================================================
let reelsGroupes      = [];
let reelViewerGroupe   = 0;
let reelViewerIndex    = 0;
let reelProgressRAF    = null;

function chargerReels() {
    const bar = document.getElementById('reelsBar');
    if (!bar) return;

    fetch(PHP + 'get_reels.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            reelsGroupes = data.groupes || [];
            renderReelsBar();
        })
        .catch(() => {});
}

function renderReelsBar() {
    document.querySelectorAll('.reels-bar').forEach(bar => {
        bar.innerHTML = '';

        // "+" Ajouter un Réel
        const addEl = document.createElement('div');
        addEl.className = 'reel-story reel-story-add';
        addEl.innerHTML = `
            <div class="reel-story-avatar"><div class="reel-story-avatar-inner"><i class="fas fa-plus"></i></div></div>
            <div class="reel-story-nom">Ajouter</div>
        `;
        addEl.addEventListener('click', ouvrirPublicationReel);
        bar.appendChild(addEl);

        reelsGroupes.forEach((groupe, idx) => {
            const el = document.createElement('div');
            el.className = 'reel-story';
            const initiales = (groupe.nom_affiche || '?').trim().substring(0, 2).toUpperCase();
            const avatarInner = groupe.photo_profil
                ? `<img src="${ROOT}${groupe.photo_profil}" alt="">`
                : escHtml(initiales);
            el.innerHTML = `
                <div class="reel-story-avatar"><div class="reel-story-avatar-inner">${avatarInner}</div></div>
                <div class="reel-story-nom">${escHtml(groupe.nom_affiche)}${groupe.is_boutique ? ' <i class="fas fa-shop"></i>' : ''}</div>
            `;
            el.addEventListener('click', () => ouvrirVisionneuseReel(idx));
            bar.appendChild(el);
        });
    });

    const etatVide = document.getElementById('reelsEtatVide');
    if (etatVide) etatVide.style.display = reelsGroupes.length ? 'none' : 'block';
}

function initReelsEvenements() {
    // ---- Publication ----
    const form = document.getElementById('formPublierReel');
    if (form) {
        document.getElementById('reel_video')?.addEventListener('change', function () {
            const lbl = document.getElementById('reelVideoLabel');
            if (lbl && this.files[0]) lbl.innerHTML = '<strong>' + escHtml(this.files[0].name) + '</strong>';
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const videoInput = document.getElementById('reel_video');
            if (!videoInput.files[0]) { showToast('Choisissez une vidéo.', 'error'); return; }
            if (videoInput.files[0].size > 25 * 1024 * 1024) {
                showToast('La vidéo dépasse 25 Mo.', 'error'); return;
            }

            const btn = document.getElementById('btnPublierReel');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publication...';

            const fd = new FormData(form);
            fetch(PHP + 'publier_reel.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Publier';
                    if (data.require_login) { window.location.href = PHP + 'login.php'; return; }
                    if (!data.success) { showToast(data.error || 'Erreur', 'error'); return; }
                    showToast('Réel publié !', 'success');
                    fermerPublicationReel();
                    form.reset();
                    document.getElementById('reelVideoLabel').innerHTML = '<strong>Cliquez</strong> pour choisir une vidéo';
                    chargerReels();
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Publier';
                    showToast('Erreur réseau.', 'error');
                });
        });
    }

    // ---- Fermer visionneuse avec Échap ----
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') fermerVisionneuseReel();
    });

    // ---- Réponse à un Réel ----
    const replyForm = document.getElementById('formReplyReel');
    if (replyForm) {
        replyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const input = document.getElementById('reelReplyInput');
            const texte = input.value.trim();
            if (!texte) return;

            const groupe = reelsGroupes[reelViewerGroupe];
            const reel   = groupe?.reels[reelViewerIndex];
            if (!groupe || !reel) return;

            fetch(PHP + 'send_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'destinataire_id=' + groupe.user_id + '&reel_id=' + reel.id + '&message=' + encodeURIComponent(texte)
            })
            .then(r => r.json())
            .then(data => {
                if (data.require_login) { window.location.href = PHP + 'login.php'; return; }
                if (data.success) {
                    input.value = '';
                    showToast('Réponse envoyée', 'success');
                } else {
                    showToast(data.error || 'Erreur', 'error');
                }
            })
            .catch(() => showToast('Erreur réseau.', 'error'));
        });
    }
}

function ouvrirPublicationReel() {
    document.getElementById('modalPublierReel')?.classList.add('open');
}
function fermerPublicationReel() {
    document.getElementById('modalPublierReel')?.classList.remove('open');
}

function ouvrirVisionneuseReel(groupeIndex) {
    reelViewerGroupe = groupeIndex;
    reelViewerIndex  = 0;
    const viewer = document.getElementById('reelViewer');
    if (!viewer) return;
    viewer.classList.add('open');
    jouerReelActuel();
}

function fermerVisionneuseReel() {
    const viewer = document.getElementById('reelViewer');
    if (!viewer || !viewer.classList.contains('open')) return;
    viewer.classList.remove('open');
    const video = document.getElementById('reelViewerVideo');
    if (video) { video.pause(); video.src = ''; }
    if (reelProgressRAF) cancelAnimationFrame(reelProgressRAF);
}

function jouerReelActuel() {
    const groupe = reelsGroupes[reelViewerGroupe];
    if (!groupe) { fermerVisionneuseReel(); return; }
    const reel = groupe.reels[reelViewerIndex];
    if (!reel) { fermerVisionneuseReel(); return; }

    const video   = document.getElementById('reelViewerVideo');
    const userEl  = document.getElementById('reelViewerUser');
    const legEl   = document.getElementById('reelViewerLegende');
    const progEl  = document.getElementById('reelProgress');

    video.src = ROOT + reel.video_url;
    video.currentTime = 0;
    video.play().catch(() => {});

    userEl.innerHTML = `<i class="fas ${groupe.is_boutique ? 'fa-shop' : 'fa-user'}"></i> ${escHtml(groupe.nom_affiche)}`;
    legEl.textContent = reel.legende || '';

    // Barres de progression (une par Réel du groupe)
    progEl.innerHTML = '';
    groupe.reels.forEach((_, i) => {
        const span = document.createElement('span');
        span.innerHTML = '<i></i>';
        if (i < reelViewerIndex) span.querySelector('i').style.width = '100%';
        progEl.appendChild(span);
    });

    // Compter la vue
    fetch(PHP + 'voir_reel.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'reel_id=' + reel.id
    }).catch(() => {});

    // Animation de la barre en cours + passage auto au suivant
    if (reelProgressRAF) cancelAnimationFrame(reelProgressRAF);
    const barreActuelle = progEl.children[reelViewerIndex]?.querySelector('i');
    function tick() {
        if (!video.duration || video.paused) { reelProgressRAF = requestAnimationFrame(tick); return; }
        const pct = Math.min(100, (video.currentTime / video.duration) * 100);
        if (barreActuelle) barreActuelle.style.width = pct + '%';
        if (pct >= 100) { reelSuivant(); return; }
        reelProgressRAF = requestAnimationFrame(tick);
    }
    reelProgressRAF = requestAnimationFrame(tick);
}

function reelSuivant() {
    const groupe = reelsGroupes[reelViewerGroupe];
    if (!groupe) return;
    if (reelViewerIndex < groupe.reels.length - 1) {
        reelViewerIndex++;
        jouerReelActuel();
    } else if (reelViewerGroupe < reelsGroupes.length - 1) {
        reelViewerGroupe++;
        reelViewerIndex = 0;
        jouerReelActuel();
    } else {
        fermerVisionneuseReel();
    }
}

function reelPrecedent() {
    if (reelViewerIndex > 0) {
        reelViewerIndex--;
        jouerReelActuel();
    } else if (reelViewerGroupe > 0) {
        reelViewerGroupe--;
        reelViewerIndex = reelsGroupes[reelViewerGroupe].reels.length - 1;
        jouerReelActuel();
    }
}

// ============================================================
//  ANIMATIONS BOUTONS — une animation différente selon le type
// ============================================================
function initButtonAnimations() {

    document.addEventListener('click', function (e) {

        // Cœur / favoris → pop
        const favBtn = e.target.closest('.btn-favori, .btn-favori-detail');
        if (favBtn) {
            favBtn.classList.remove('pop');
            void favBtn.offsetWidth; // relance l'animation même si déjà jouée
            favBtn.classList.add('pop');
            setTimeout(() => favBtn.classList.remove('pop'), 500);
        }

        // Supprimer / confirmer suppression → secousse
        const dangerBtn = e.target.closest('.pa-btn-supp, .btn-confirmer, .panier-item-suppr');
        if (dangerBtn) {
            dangerBtn.classList.remove('shake');
            void dangerBtn.offsetWidth;
            dangerBtn.classList.add('shake');
            setTimeout(() => dangerBtn.classList.remove('shake'), 450);
        }

        // Envoi de message → avion en papier
        const sendBtn = e.target.closest('.chat-input-area button');
        if (sendBtn) {
            sendBtn.classList.remove('fly');
            void sendBtn.offsetWidth;
            sendBtn.classList.add('fly');
            setTimeout(() => sendBtn.classList.remove('fly'), 550);
        }

        // Filtres → petit rebond
        const filterBtn = e.target.closest('.btn-filter');
        if (filterBtn) {
            filterBtn.classList.remove('bounce');
            void filterBtn.offsetWidth;
            filterBtn.classList.add('bounce');
            setTimeout(() => filterBtn.classList.remove('bounce'), 400);
        }

        // Confirmation de réception → pulse de validation
        const confirmReceptionBtn = e.target.closest('.btn-confirmer-reception');
        if (confirmReceptionBtn) {
            confirmReceptionBtn.classList.remove('pulse-ok');
            void confirmReceptionBtn.offsetWidth;
            confirmReceptionBtn.classList.add('pulse-ok');
        }

        // Boutons standards / primaires → effet ripple
        const rippleBtn = e.target.closest(
            '.btn-primary, .btn-secondary, .btn-primary-full, .btn-connexion, ' +
            '.btn-inscription, .btn-detail, .btn-contact, .btn-action, .pa-btn-voir, .btn-sauv, ' +
            '.btn-signaler, .btn-payer-vendeur, .btn-rembourser'
        );
        if (rippleBtn) createRipple(rippleBtn, e);
    });
}

function createRipple(btn, e) {
    btn.classList.add('ripple-wrap');
    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const span = document.createElement('span');
    span.className = 'ripple';
    span.style.width  = span.style.height = size + 'px';
    span.style.left = (e.clientX - rect.left - size / 2) + 'px';
    span.style.top  = (e.clientY - rect.top  - size / 2) + 'px';
    btn.appendChild(span);
    setTimeout(() => span.remove(), 600);
}

// ============================================================
//  SUGGESTIONS DE RECHERCHE
// ============================================================
function fetchSuggestions(query) {
    fetch(PHP + 'search.php?q=' + encodeURIComponent(query))
        .then(r => r.json())
        .then(results => showSuggestions(results))
        .catch(() => hideSuggestions());
}

function showSuggestions(results) {
    hideSuggestions();
    if (!results.length) return;

    const input = document.querySelector('.search-bar input[name="search"]');
    if (!input) return;

    const box = document.createElement('div');
    box.id = 'search-suggestions';
    box.style.cssText = `
        position:absolute;top:100%;left:0;right:0;
        background:white;border:1px solid #eee;border-radius:12px;
        box-shadow:0 8px 25px rgba(0,0,0,0.12);z-index:9999;
        max-height:320px;overflow-y:auto;margin-top:4px;
    `;

    results.slice(0, 6).forEach(a => {
        const item = document.createElement('a');
        item.href = PHP + 'article.php?id=' + a.id;
        item.style.cssText = 'display:flex;align-items:center;gap:12px;padding:10px 14px;text-decoration:none;color:#333;border-bottom:1px solid #f5f5f5;';
        item.innerHTML = `
            <img src="${a.premiere_photo || 'uploads/articles/default.jpg'}"
                 style="width:40px;height:40px;object-fit:cover;border-radius:6px;flex-shrink:0;">
            <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:0.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(a.titre)}</div>
                <div style="color:#e94560;font-size:0.82rem;font-weight:600;">${a.prix_formate || ''}</div>
            </div>
        `;
        item.addEventListener('mouseover', () => item.style.background = '#f8f8f8');
        item.addEventListener('mouseout',  () => item.style.background = '');
        box.appendChild(item);
    });

    const wrap = input.closest('.search-bar form');
    if (wrap) {
        wrap.style.position = 'relative';
        wrap.appendChild(box);
    }
}

function hideSuggestions() {
    const box = document.getElementById('search-suggestions');
    if (box) box.remove();
}

// ============================================================
//  BADGE MESSAGES
// ============================================================
function checkMessagesBadge() {
    fetch(PHP + 'check_messages.php')
        .then(r => r.json())
        .then(data => {
            const links = document.querySelectorAll('a[href*="messagerie"]');
            links.forEach(link => {
                let badge = link.querySelector('.badge');
                if (data.new_messages > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'badge';
                        link.appendChild(badge);
                    }
                    badge.textContent = data.new_messages;
                } else if (badge) {
                    badge.remove();
                }
            });
        })
        .catch(() => {});
}

// ============================================================
//  FAVORIS (utilisable depuis n'importe quelle page)
// ============================================================
function toggleFavori(articleId, btn) {
    fetch(PHP + 'toggle_favor.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'article_id=' + articleId
    })
    .then(r => r.json())
    .then(data => {
        if (data.require_login) { window.location.href = PHP + 'login.php'; return; }
        if (!data.success) return;
        if (btn) {
            const icon = btn.querySelector('i');
            btn.classList.toggle('active', data.added);
            if (icon) icon.className = data.added ? 'fas fa-heart' : 'far fa-heart';
            btn.title = data.added ? 'Retirer des favoris' : 'Ajouter aux favoris';
        }
        showToast(data.message || (data.added ? 'Ajouté aux favoris' : 'Retiré des favoris'), 'success');
    })
    .catch(console.error);
}

// ============================================================
//  ANIMATION AU SCROLL
// ============================================================
function initScrollAnimation() {
    if (!window.IntersectionObserver) return;
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity    = '1';
                entry.target.style.transform  = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.article-card, .category-card, .step, .testimonial-card').forEach(el => {
        el.style.opacity    = '0';
        el.style.transform  = 'translateY(20px)';
        el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        observer.observe(el);
    });
}

// ============================================================
//  UTILITAIRES
// ============================================================
function formatPrice(price) {
    return new Intl.NumberFormat('fr-FR').format(price);
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = String(str || '');
    return d.innerHTML;
}

function showToast(message, type = 'success') {
    const existing = document.getElementById('mb-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'mb-toast';
    toast.style.cssText = `
        position:fixed;bottom:20px;right:20px;z-index:99999;
        padding:12px 22px;border-radius:10px;
        font-family:'Poppins',sans-serif;font-size:0.9rem;font-weight:500;color:white;
        background:${type === 'success' ? '#00a86b' : '#e94560'};
        box-shadow:0 4px 16px rgba(0,0,0,0.2);
        animation:slideInToast 0.3s ease;pointer-events:none;
    `;
    toast.textContent = message;

    if (!document.getElementById('toast-style')) {
        const s = document.createElement('style');
        s.id = 'toast-style';
        s.textContent = '@keyframes slideInToast{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}';
        document.head.appendChild(s);
    }

    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

// ============================================================
//  BOÎTES DE DIALOGUE (.modal-reel) — Annuler / clic dehors / Échap
//  Valable pour toutes les pages : signalement, avis, publication de Réel...
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal-reel').forEach(function (modal) {
        // Bouton "Annuler" ajouté automatiquement sous le formulaire
        const form = modal.querySelector('form');
        if (form && !form.querySelector('.btn-annuler-modal')) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-annuler-modal';
            btn.textContent = 'Annuler';
            btn.addEventListener('click', function () { modal.classList.remove('open'); });
            form.appendChild(btn);
        }
        // Clic sur le fond sombre = fermer
        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.classList.remove('open');
        });
    });

    // Touche Échap = fermer la boîte ouverte
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-reel.open').forEach(function (m) { m.classList.remove('open'); });
        }
    });
});
