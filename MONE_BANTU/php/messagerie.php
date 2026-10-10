<?php
// ============================================================
//  MONË BANTU — Messagerie style Messenger
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

function tt(string $cle, string $defaut): string {
    return function_exists('t') ? t($cle) : $defaut;
}

if (!function_exists('estConnecte') || !estConnecte()) {
    redirect($base_path . 'php/login.php');
}
if (function_exists('exigerLocalisation')) {
    exigerLocalisation($pdo, $base_path);
}

$user_id       = userId();
$selected_user = (int)($_GET['user'] ?? 0);

// ---- Liste des conversations ----
$conversations = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            autre_id,
            u.pseudo,
            derniere_date,
            (
                SELECT contenu FROM messages
                WHERE (expediteur_id = :uid  AND destinataire_id = autre_id)
                   OR (expediteur_id = autre_id AND destinataire_id = :uid2)
                ORDER BY date_envoi DESC LIMIT 1
            ) AS dernier_message,
            (
                SELECT type FROM messages
                WHERE (expediteur_id = :uid3 AND destinataire_id = autre_id)
                   OR (expediteur_id = autre_id AND destinataire_id = :uid4)
                ORDER BY date_envoi DESC LIMIT 1
            ) AS dernier_type,
            (
                SELECT COUNT(*) FROM messages
                WHERE expediteur_id = autre_id
                  AND destinataire_id = :uid5
                  AND lu = 0
            ) AS non_lus
        FROM (
            SELECT
                CASE WHEN expediteur_id = :uid6 THEN destinataire_id ELSE expediteur_id END AS autre_id,
                MAX(date_envoi) AS derniere_date
            FROM messages
            WHERE expediteur_id = :uid7 OR destinataire_id = :uid8
            GROUP BY autre_id
        ) AS convs
        JOIN utilisateurs u ON u.id = autre_id
        ORDER BY derniere_date DESC
    ");
    $stmt->execute([
        ':uid'  => $user_id, ':uid2' => $user_id, ':uid3' => $user_id,
        ':uid4' => $user_id, ':uid5' => $user_id, ':uid6' => $user_id,
        ':uid7' => $user_id, ':uid8' => $user_id,
    ]);
    $conversations = $stmt->fetchAll();
} catch (Throwable $e) {}

// ---- Utilisateur sélectionné ----
$autre_user = null;
if ($selected_user > 0) {
    $s = $pdo->prepare("SELECT id, pseudo FROM utilisateurs WHERE id = ?");
    $s->execute([$selected_user]);
    $autre_user = $s->fetch();
    if (!$autre_user) $selected_user = 0;
}

$my_pseudo = $_SESSION['user_pseudo'] ?? 'Moi';

// ---- Valeurs préparées pour JS (compatibles PHP 8) ----
$dest_init_val = '';
if ($autre_user) {
    $dest_init_val = function_exists('initiales') ? initiales($autre_user['pseudo']) : strtoupper(substr($autre_user['pseudo'], 0, 2));
}
$my_init_val  = function_exists('initiales') ? initiales($my_pseudo) : strtoupper(substr($my_pseudo, 0, 2));
$has_conv_val = ($selected_user > 0 && $autre_user) ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — MONË BANTU</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background:#f0f2f5; }
        .footer { display:none; }

        .navbar .container { display: flex; align-items: center; gap: 15px; flex-wrap: nowrap; }
        .navbar .logo { flex-shrink: 0; }
        .navbar .search-bar { flex: 1; max-width: 420px; margin: 0 auto; }
        .nav-toggle-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 16px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; border: none; border-radius: 50px;
            font-family: 'Poppins', sans-serif; font-size: 0.9rem;
            font-weight: 600; cursor: pointer; flex-shrink: 0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .nav-toggle-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(233,69,96,0.4); }
        .nav-panel {
            position: fixed; top: 0; right: -340px;
            width: 320px; max-width: 85vw; height: 100vh;
            background: white; box-shadow: -4px 0 24px rgba(0,0,0,0.15);
            z-index: 99999; display: flex; flex-direction: column;
            transition: right 0.3s ease; overflow-y: auto;
        }
        .nav-panel.open { right: 0; }
        .nav-panel-header {
            padding: 18px 20px; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; background: white; z-index: 2;
        }
        .nav-panel-header h3 { font-size: 1.05rem; color: var(--text); margin: 0; }
        .nav-panel-close {
            background: none; border: none; font-size: 1.3rem;
            color: var(--text-muted); cursor: pointer;
            width: 34px; height: 34px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s, transform 0.2s;
        }
        .nav-panel-close:hover { background: #f0f0f0; transform: rotate(90deg); }
        .nav-panel-user {
            padding: 14px 20px; background: #f9f9fc;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 12px;
        }
        .nav-panel-user-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; display: flex; align-items: center; justify-content: center;
            font-weight: 700; flex-shrink: 0;
        }
        .nav-panel-user-info strong { display: block; font-size: 0.92rem; }
        .nav-panel-user-info small { color: var(--text-muted); font-size: 0.78rem; }
        .nav-panel-list { list-style: none; padding: 8px 0; margin: 0; flex: 1; }
        .nav-panel-list a {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 20px; color: var(--text);
            text-decoration: none; font-size: 0.92rem;
            transition: background 0.15s, color 0.15s;
        }
        .nav-panel-list a:hover { background: rgba(233,69,96,0.06); color: var(--primary); }
        .nav-panel-list a.active { background: rgba(233,69,96,0.1); color: var(--primary); font-weight: 600; }
        .nav-panel-list a i { width: 22px; text-align: center; font-size: 1rem; color: var(--primary); flex-shrink: 0; }
        .nav-panel-langue {
            padding: 14px 20px; border-top: 1px solid var(--border);
            display: flex; gap: 10px; align-items: center; font-size: 0.85rem;
        }
        .nav-panel-langue span { color: var(--text-muted); }
        .nav-panel-langue a {
            padding: 5px 12px; border-radius: 50px; text-decoration: none;
            color: var(--text-muted); font-weight: 600; transition: all 0.2s;
        }
        .nav-panel-langue a.actif { background: rgba(233,69,96,0.12); color: var(--primary); }
        .nav-panel-logout { padding: 14px 20px; border-top: 1px solid var(--border); }
        .nav-panel-logout a {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 11px; background: #f0f0f0; color: #c0392b;
            border-radius: 8px; text-decoration: none;
            font-weight: 600; font-size: 0.9rem; transition: background 0.2s;
        }
        .nav-panel-logout a:hover { background: #ffe5e5; }
        .nav-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.45);
            z-index: 99998; opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .nav-overlay.open { opacity: 1; pointer-events: auto; }
        .nav-menu { display: none !important; }
        .hamburger { display: none !important; }
        @media (max-width: 700px) {
            .nav-toggle-btn span { display: none; }
            .nav-toggle-btn { padding: 10px 12px; }
            .nav-panel { width: 90vw; right: -100vw; }
        }

        /* ============================================================
           MESSENGER
           ============================================================ */
        .messenger-wrap {
            display: grid;
            grid-template-columns: 300px 1fr;
            height: calc(100vh - 70px);
            background: white;
            overflow: hidden;
        }

        .sidebar { display: flex; flex-direction: column; border-right: 1px solid #eee; }
        .sidebar-top { padding: 16px; border-bottom: 1px solid #f0f0f0; }
        .sidebar-top h2 { font-size:1.2rem; font-weight:700; margin-bottom:10px; }
        .sidebar-search { position:relative; }
        .sidebar-search i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:#aaa; font-size:0.85rem; }
        .sidebar-search input {
            width:100%; padding:8px 12px 8px 32px;
            background:#f0f2f5; border:none; border-radius:50px;
            font-family:'Poppins',sans-serif; font-size:0.85rem; outline:none;
        }
        .conv-list { flex:1; overflow-y:auto; }
        .conv-list::-webkit-scrollbar { width:3px; }
        .conv-list::-webkit-scrollbar-thumb { background:#ddd; }

        .conv-item {
            display:flex; align-items:center;
            padding:12px 14px; gap:11px;
            cursor:pointer; text-decoration:none; color:inherit;
            border-bottom:1px solid #f9f9f9;
            transition:background 0.15s;
        }
        .conv-item:hover  { background:#f5f5f5; }
        .conv-item.active { background:#fff0f2; }

        .conv-av {
            width:46px; height:46px; border-radius:50%; flex-shrink:0;
            background:linear-gradient(135deg,#e94560,#0f3460);
            color:white; display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:1rem;
        }
        .conv-body { flex:1; min-width:0; }
        .conv-row1 { display:flex; justify-content:space-between; align-items:center; }
        .conv-name { font-weight:600; font-size:0.9rem; }
        .conv-time { font-size:0.7rem; color:#aaa; }
        .conv-row2 { display:flex; justify-content:space-between; align-items:center; margin-top:2px; }
        .conv-prev { font-size:0.78rem; color:#888; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1; }
        .badge-unread {
            background:#e94560; color:white;
            font-size:0.68rem; font-weight:700;
            min-width:18px; height:18px; border-radius:9px;
            display:flex; align-items:center; justify-content:center;
            padding:0 4px; margin-left:6px; flex-shrink:0;
        }
        .no-conv { text-align:center; padding:40px 16px; color:#aaa; }
        .no-conv i { font-size:2.5rem; display:block; margin-bottom:10px; }

        .chat-wrap { display:flex; flex-direction:column; height:100%; }
        .chat-header {
            display:flex; align-items:center; gap:12px;
            padding:13px 18px; background:white;
            border-bottom:1px solid #eee; flex-shrink:0;
        }
        .chat-hav {
            width:38px; height:38px; border-radius:50%;
            background:linear-gradient(135deg,#e94560,#0f3460);
            color:white; display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:0.85rem; flex-shrink:0;
        }
        .chat-hinfo { flex:1; }
        .chat-hinfo h3 { font-size:0.95rem; }
        .chat-hinfo small { font-size:0.72rem; color:#00c853; }
        .chat-hbtns { display:flex; gap:6px; }
        .chat-hbtns button {
            width:34px; height:34px; border-radius:50%; border:none;
            background:#f0f2f5; color:#555; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            transition:all 0.2s; font-size:0.9rem;
        }
        .chat-hbtns button:hover { background:#e94560; color:white; }

        .msgs-area {
            flex:1; overflow-y:auto; padding:14px 18px;
            display:flex; flex-direction:column; gap:3px;
            background:#fafbfc;
        }
        .msgs-area::-webkit-scrollbar { width:3px; }
        .msgs-area::-webkit-scrollbar-thumb { background:#ddd; }

        .date-sep { text-align:center; margin:10px 0; }
        .date-sep span {
            background:#e8e8e8; color:#888;
            font-size:0.72rem; padding:3px 12px; border-radius:50px;
        }
        .msg-row { display:flex; align-items:flex-end; gap:6px; margin:1px 0; }
        .msg-row.sent     { flex-direction:row-reverse; }
        .msg-row.received { flex-direction:row; }

        .msg-mini-av {
            width:26px; height:26px; border-radius:50%;
            background:linear-gradient(135deg,#e94560,#0f3460);
            color:white; font-size:0.62rem; font-weight:700;
            display:flex; align-items:center; justify-content:center;
            flex-shrink:0;
        }
        .msg-row.sent .msg-mini-av { display:none; }

        .msg-bubble { max-width:62%; position:relative; }
        .msg-inner {
            padding:9px 13px; border-radius:18px;
            font-size:0.88rem; line-height:1.5; word-wrap:break-word;
        }
        .msg-row.sent     .msg-inner {
            background:linear-gradient(135deg,#e94560,#c73652);
            color:white; border-bottom-right-radius:4px;
        }
        .msg-row.received .msg-inner {
            background:white; color:#1a1a2e;
            border-bottom-left-radius:4px;
            box-shadow:0 1px 3px rgba(0,0,0,0.08);
        }
        .msg-time {
            font-size:0.65rem; color:#aaa;
            margin-top:3px; display:flex; gap:4px; align-items:center;
        }
        .msg-row.sent .msg-time { justify-content:flex-end; }

        .msg-img  { max-width:220px; border-radius:12px; cursor:pointer; display:block; }
        .msg-vid  { max-width:240px; border-radius:12px; display:block; }
        .msg-aud  { width:200px; height:34px; border-radius:20px; }
        .msg-file-link {
            display:flex; align-items:center; gap:8px;
            padding:8px 10px; background:rgba(255,255,255,0.15);
            border-radius:10px; text-decoration:none; color:inherit;
        }
        .msg-row.received .msg-file-link { background:#f0f2f5; color:#333; }
        .msg-file-link i { font-size:1.3rem; color:#e94560; flex-shrink:0; }
        .msg-file-name { font-size:0.8rem; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:150px; }
        .msg-file-size { font-size:0.7rem; opacity:0.7; }

        .msg-reactions { display:flex; gap:3px; flex-wrap:wrap; margin-top:3px; }
        .msg-row.sent .msg-reactions { justify-content:flex-end; }
        .react-pill {
            background:white; border:1px solid #eee; border-radius:50px;
            padding:1px 6px; font-size:0.72rem;
            box-shadow:0 1px 2px rgba(0,0,0,0.06);
        }
        .msg-bubble:hover .react-btn { opacity:1; }
        .react-btn {
            position:absolute; top:-10px;
            opacity:0; transition:opacity 0.2s; z-index:10;
            background:white; border:1px solid #eee; border-radius:50%;
            width:24px; height:24px; display:flex; align-items:center;
            justify-content:center; font-size:0.72rem; cursor:pointer;
            box-shadow:0 2px 6px rgba(0,0,0,0.1);
        }
        .msg-row.sent     .react-btn { left:-28px; }
        .msg-row.received .react-btn { right:-28px; }

        .quick-emojis {
            position:absolute; background:white; border-radius:50px;
            padding:5px 10px; box-shadow:0 4px 18px rgba(0,0,0,0.14);
            display:flex; gap:5px; z-index:100; top:-44px; white-space:nowrap;
        }
        .msg-row.sent     .quick-emojis { right:0; }
        .msg-row.received .quick-emojis { left:0; }
        .quick-emojis span { font-size:1.25rem; cursor:pointer; line-height:1; }

        .msg-bubble:hover .pin-btn { opacity:1; }
        .pin-btn {
            position:absolute; top:-10px;
            opacity:0; transition:opacity 0.2s; z-index:10;
            background:white; border:1px solid #eee; border-radius:50%;
            width:24px; height:24px; display:flex; align-items:center;
            justify-content:center; font-size:0.7rem; cursor:pointer; color:#e94560;
            box-shadow:0 2px 6px rgba(0,0,0,0.1);
        }
        .msg-row.sent     .pin-btn { left:-56px; }
        .msg-row.received .pin-btn { right:-56px; }
        .pin-menu {
            position:absolute; background:white; border-radius:12px;
            padding:8px 0; box-shadow:0 4px 18px rgba(0,0,0,0.16);
            z-index:100; top:-8px; white-space:nowrap; min-width:170px;
        }
        .msg-row.sent     .pin-menu { right:0; }
        .msg-row.received .pin-menu { left:0; }
        .pin-menu span {
            display:flex; align-items:center; gap:8px;
            padding:8px 16px; font-size:0.82rem; cursor:pointer;
        }
        .pin-menu span:hover { background:#f5f5f5; }
        .pin-menu span.pin-annuler { color:#999; border-top:1px solid #eee; margin-top:4px; }

        .msg-bubble.epinglee { border:1px solid rgba(233,69,96,0.35); }
        .msg-pin-tag {
            position:absolute; top:-8px;
            background:var(--primary); color:white; width:18px; height:18px;
            border-radius:50%; display:flex; align-items:center; justify-content:center;
            font-size:0.55rem;
        }
        .msg-row.sent     .msg-pin-tag { right:-6px; }
        .msg-row.received .msg-pin-tag { left:-6px; }

        .barre-epingles {
            display:flex; gap:8px; overflow-x:auto; padding:8px 12px;
            background:#fff8ec; border-bottom:1px solid #f5e0b8;
        }
        .epingle-item {
            display:flex; align-items:center; gap:6px; flex-shrink:0;
            background:white; border:1px solid #f0d18a; border-radius:50px;
            padding:5px 12px; font-size:0.78rem; cursor:pointer; color:#8a6100;
        }
        .epingle-item i { color:var(--primary); font-size:0.7rem; }

        .chat-empty {
            flex:1; display:flex; flex-direction:column;
            align-items:center; justify-content:center;
            text-align:center; color:#aaa; gap:10px;
        }
        .chat-empty i { font-size:3.5rem; color:#e8e8e8; }
        .chat-empty h3 { color:#555; }

        .input-bar {
            background:white; border-top:1px solid #eee;
            padding:10px 14px; flex-shrink:0;
        }
        .upload-prev {
            display:none; align-items:center; gap:10px;
            padding:7px 12px; background:#f8f8f8;
            border-radius:10px; margin-bottom:8px;
        }
        .upload-prev.show { display:flex; }
        .upload-prev .thumb img,
        .upload-prev .thumb video {
            width:46px; height:46px; object-fit:cover; border-radius:7px;
        }
        .upload-prev .thumb-icon {
            width:46px; height:46px; background:#eee;
            border-radius:7px; display:flex; align-items:center;
            justify-content:center; font-size:1.4rem;
        }
        .prev-name { font-size:0.8rem; font-weight:600; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .prev-size { font-size:0.7rem; color:#aaa; }
        .btn-cancel-up { background:none; border:none; color:#e94560; cursor:pointer; font-size:1rem; }

        .caption-inp {
            display:none; width:100%; padding:6px 12px;
            border:1px solid #eee; border-radius:8px;
            font-family:'Poppins',sans-serif; font-size:0.85rem;
            margin-bottom:8px; outline:none;
        }
        .caption-inp.show { display:block; }

        .input-row { display:flex; align-items:center; gap:6px; }
        .input-actions { display:flex; gap:2px; }
        .ia-btn {
            width:34px; height:34px; border-radius:50%; border:none;
            background:none; color:#888; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            font-size:1rem; transition:all 0.2s; flex-shrink:0;
        }
        .ia-btn:hover { background:#f0f0f0; color:#e94560; }

        .txt-wrap { flex:1; position:relative; }
        #msgInput {
            width:100%; padding:9px 15px;
            border:1.5px solid #e8e8e8; border-radius:22px;
            font-family:'Poppins',sans-serif; font-size:0.9rem;
            background:#f5f5f5; outline:none; resize:none;
            min-height:38px; max-height:110px; overflow-y:auto;
            transition:border-color 0.2s, background 0.2s; line-height:1.4;
        }
        #msgInput:focus { border-color:#e94560; background:white; }

        .rec-timer { font-size:0.78rem; color:#e94560; font-weight:600; display:none; min-width:36px; text-align:center; }
        .rec-timer.show { display:block; }
        .rec-btn.recording { background:#e94560 !important; color:white !important; }

        .send-btn {
            width:38px; height:38px; border-radius:50%; border:none;
            background:linear-gradient(135deg,#e94560,#c73652);
            color:white; cursor:pointer; flex-shrink:0;
            display:flex; align-items:center; justify-content:center;
            transition:all 0.2s;
        }
        .send-btn:hover { transform:scale(1.08); }
        .send-btn:disabled { opacity:0.4; transform:none; cursor:default; }

        .emoji-picker-wrap { position:relative; }
        .ep-box {
            display:none; position:absolute; bottom:calc(100% + 6px); left:0;
            background:white; border-radius:14px;
            box-shadow:0 8px 28px rgba(0,0,0,.13);
            padding:10px; width:290px; z-index:200;
        }
        .ep-box.show { display:block; }
        .ep-cats { display:flex; gap:4px; margin-bottom:8px; overflow-x:auto; }
        .ep-cat {
            background:none; border:none; cursor:pointer;
            font-size:1.05rem; padding:3px 7px; border-radius:7px; flex-shrink:0;
        }
        .ep-cat.on, .ep-cat:hover { background:#f0f0f0; }
        .ep-grid { display:grid; grid-template-columns:repeat(8,1fr); gap:2px; max-height:170px; overflow-y:auto; }
        .ep-emoji { background:none; border:none; cursor:pointer; font-size:1.2rem; padding:3px; border-radius:5px; }
        .ep-emoji:hover { background:#f0f0f0; }

        .media-modal {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,.9); z-index:9999;
            align-items:center; justify-content:center;
        }
        .media-modal.open { display:flex; }
        .media-modal img, .media-modal video {
            max-width:92vw; max-height:90vh; object-fit:contain; border-radius:8px;
        }
        .mm-close {
            position:absolute; top:14px; right:14px;
            background:white; border:none; width:38px; height:38px;
            border-radius:50%; font-size:1.1rem; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
        }

        @media(max-width:768px) {
            .messenger-wrap { grid-template-columns:1fr; height:auto; }
            .sidebar { display:none; }
            .sidebar.show { display:flex; height:calc(100vh - 70px); }
            .chat-wrap { height:calc(100vh - 70px); }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <a href="<?php echo $base_path; ?>index.php"><h1>MONË <span>BANTU</span></h1></a>
            </div>
            <div class="search-bar">
                <form action="acheter.php" method="GET">
                    <input type="text" name="search" placeholder="Rechercher un article...">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <button class="nav-toggle-btn" onclick="ouvrirNavPanel()">
                <i class="fas fa-bars"></i>
                <span>Menu</span>
            </button>
        </div>
    </nav>

<div class="messenger-wrap">

    <!-- ===== SIDEBAR CONVERSATIONS ===== -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-top">
            <h2><i class="fas fa-comments" style="color:#e94560;margin-right:7px;font-size:1rem;"></i>Messages</h2>
            <div class="sidebar-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher…" oninput="filtrerConv(this.value)">
            </div>
        </div>
        <div class="conv-list" id="convList">
            <?php if (empty($conversations)): ?>
                <div class="no-conv">
                    <i class="fas fa-comment-slash"></i>
                    <p>Aucune conversation</p>
                    <a href="acheter.php" style="color:#e94560;font-size:0.82rem;margin-top:6px;display:block;">Explorer les articles</a>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $c):
                    $d    = new DateTime($c['derniere_date']);
                    $now  = new DateTime();
                    $diff = $now->diff($d);
                    $h    = $diff->days === 0 ? $d->format('H:i') : ($diff->days === 1 ? 'Hier' : $d->format('d/m'));
                    $dt   = $c['dernier_type'] ?? 'texte';
                    if ($dt === 'image')   { $prev = '📷 Photo'; }
                    elseif ($dt === 'video') { $prev = '🎥 Vidéo'; }
                    elseif ($dt === 'audio') { $prev = '🎤 Vocal'; }
                    elseif ($dt === 'fichier'){ $prev = '📎 Fichier'; }
                    else { $prev = mb_substr($c['dernier_message'] ?? '', 0, 36); }
                ?>
                <a href="messagerie.php?user=<?php echo $c['autre_id']; ?>"
                   class="conv-item <?php echo $selected_user == $c['autre_id'] ? 'active' : ''; ?>"
                   data-nom="<?php echo function_exists('esc') ? strtolower(esc($c['pseudo'])) : strtolower(htmlspecialchars($c['pseudo'])); ?>">
                    <div class="conv-av"><?php echo function_exists('initiales') ? initiales($c['pseudo']) : strtoupper(substr($c['pseudo'],0,2)); ?></div>
                    <div class="conv-body">
                        <div class="conv-row1">
                            <span class="conv-name"><?php echo function_exists('esc') ? esc($c['pseudo']) : htmlspecialchars($c['pseudo']); ?></span>
                            <span class="conv-time"><?php echo $h; ?></span>
                        </div>
                        <div class="conv-row2">
                            <span class="conv-prev"><?php echo function_exists('esc') ? esc($prev) : htmlspecialchars($prev); ?></span>
                            <?php if ($c['non_lus'] > 0): ?>
                                <span class="badge-unread"><?php echo (int)$c['non_lus']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== ZONE CHAT ===== -->
    <div class="chat-wrap">
        <?php if ($selected_user > 0 && $autre_user): ?>

        <div class="chat-header">
            <div class="chat-hav"><?php echo function_exists('initiales') ? initiales($autre_user['pseudo']) : strtoupper(substr($autre_user['pseudo'],0,2)); ?></div>
            <div class="chat-hinfo">
                <h3><?php echo function_exists('esc') ? esc($autre_user['pseudo']) : htmlspecialchars($autre_user['pseudo']); ?></h3>
                <small><i class="fas fa-circle" style="font-size:0.5rem;"></i> En ligne</small>
            </div>
            <div class="chat-hbtns">
                <button title="Voir les articles" onclick="window.location.href='acheter.php?search=<?php echo urlencode($autre_user['pseudo']); ?>'">
                    <i class="fas fa-store"></i>
                </button>
                <button title="Signaler" onclick="ouvrirSignalementUtilisateur(<?php echo (int)$autre_user['id']; ?>, 'message', null)">
                    <i class="fas fa-flag"></i>
                </button>
            </div>
        </div>

        <div class="msgs-area" id="msgsArea">
            <div style="text-align:center;padding:30px;color:#aaa;">
                <i class="fas fa-spinner fa-spin" style="font-size:1.4rem;color:#e94560;"></i>
            </div>
        </div>

        <div class="input-bar">
            <div class="upload-prev" id="upPrev">
                <div class="thumb" id="upThumb"></div>
                <div style="flex:1;min-width:0;">
                    <div class="prev-name" id="upName"></div>
                    <div class="prev-size" id="upSize"></div>
                </div>
                <button class="btn-cancel-up" onclick="annulerUp()"><i class="fas fa-times"></i></button>
            </div>
            <input type="text" class="caption-inp" id="captionInp" placeholder="Légende (optionnel)…">

            <div class="input-row">
                <div class="input-actions">
                    <div class="emoji-picker-wrap">
                        <button class="ia-btn" id="epToggle" title="Emojis" onclick="toggleEP()">
                            <i class="far fa-smile"></i>
                        </button>
                        <div class="ep-box" id="epBox">
                            <div class="ep-cats" id="epCats"></div>
                            <div class="ep-grid" id="epGrid"></div>
                        </div>
                    </div>
                    <button class="ia-btn" title="Photo" onclick="document.getElementById('inPhoto').click()">
                        <i class="fas fa-image"></i>
                    </button>
                    <input type="file" id="inPhoto" accept="image/*" style="display:none" onchange="prevUp(this,'image')">
                    <button class="ia-btn" title="Vidéo" onclick="document.getElementById('inVideo').click()">
                        <i class="fas fa-video"></i>
                    </button>
                    <input type="file" id="inVideo" accept="video/*" style="display:none" onchange="prevUp(this,'video')">
                    <button class="ia-btn" title="Fichier" onclick="document.getElementById('inFile').click()">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <input type="file" id="inFile" style="display:none" onchange="prevUp(this,'fichier')">
                </div>

                <div class="txt-wrap">
                    <textarea id="msgInput" rows="1"
                        placeholder="Écrire un message…"
                        oninput="autoGrow(this);majSendBtn()"
                        onkeydown="handleKey(event)"></textarea>
                </div>

                <span class="rec-timer" id="recTimer">0:00</span>
                <button class="ia-btn rec-btn" id="recBtn" title="Vocal" onclick="toggleRec()">
                    <i class="fas fa-microphone"></i>
                </button>
                <button class="send-btn" id="sendBtn" onclick="envoyer()" disabled>
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>

        <?php else: ?>
        <div class="chat-empty">
            <i class="fas fa-comment-dots"></i>
            <h3>Sélectionnez une conversation</h3>
            <p>ou <a href="acheter.php" style="color:#e94560;">contactez un vendeur</a></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal média -->
<div class="media-modal" id="mediaModal" onclick="fermerMM()">
    <button class="mm-close" onclick="fermerMM()"><i class="fas fa-times"></i></button>
    <div id="mmContent" onclick="event.stopPropagation()"></div>
</div>

<!-- ===== PANNEAU MENU ===== -->
<div class="nav-overlay" id="navOverlay" onclick="fermerNavPanel()"></div>
<aside class="nav-panel" id="navPanel">
    <div class="nav-panel-header">
        <h3><i class="fas fa-bars" style="color:var(--primary)"></i> Menu</h3>
        <button class="nav-panel-close" onclick="fermerNavPanel()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="nav-panel-user">
        <div class="nav-panel-user-avatar">
            <?php echo $my_init_val; ?>
        </div>
        <div class="nav-panel-user-info">
            <strong><?php echo function_exists('esc') ? esc($my_pseudo) : htmlspecialchars($my_pseudo); ?></strong>
            <small>Monë Bantu</small>
        </div>
    </div>

    <ul class="nav-panel-list">
        <li><a href="acheter.php"><i class="fas fa-store"></i> Acheter</a></li>
        <li><a href="actualites.php"><i class="fas fa-newspaper"></i> Actualités</a></li>
        <li><a href="vendre.php"><i class="fas fa-plus-circle"></i> Vendre</a></li>
        <li><a href="boutique.php"><i class="fas fa-shop"></i> Boutique</a></li>
        <li><a href="reels.php"><i class="fas fa-clapperboard"></i> Réels</a></li>
        <li><a href="panier.php"><i class="fas fa-cart-shopping"></i> Panier</a></li>
        <li><a href="mes_achats.php"><i class="fas fa-bag-shopping"></i> Mes achats</a></li>
        <li><a href="mes_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
        <li><a href="messagerie.php" class="active"><i class="fas fa-envelope"></i> Messages</a></li>
        <li><a href="profil.php"><i class="fas fa-user"></i> Mon profil</a></li>
    </ul>

    <div class="nav-panel-langue">
        <span><i class="fas fa-globe"></i> Langue :</span>
        <a href="definir_langue.php?langue=fr" class="<?php echo ($GLOBALS['LANGUE_ACTIVE'] ?? 'fr') === 'fr' ? 'actif' : ''; ?>">FR</a>
        <a href="definir_langue.php?langue=en" class="<?php echo ($GLOBALS['LANGUE_ACTIVE'] ?? 'fr') === 'en' ? 'actif' : ''; ?>">EN</a>
    </div>

    <div class="nav-panel-logout">
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>
</aside>

<!-- ===== MODAL SIGNALEMENT ===== -->
<div class="modal-reel" id="modalSignalementUser">
    <div class="modal-reel-box">
        <button class="modal-reel-fermer" onclick="fermerSignalementUtilisateur()"><i class="fas fa-times"></i></button>
        <h3><i class="fas fa-flag"></i> Signaler cet utilisateur</h3>
        <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
            Décris le problème — l'équipe Monë Bantu va l'examiner.
        </p>
        <form id="formSignalementUser">
            <div class="form-group">
                <label style="font-size:0.85rem;">Gravité</label>
                <div class="mode-virement-choix">
                    <label class="mode-virement-opt"><input type="radio" name="gravite_signalement" value="leger"> Léger</label>
                    <label class="mode-virement-opt"><input type="radio" name="gravite_signalement" value="moyen" checked> Moyen</label>
                    <label class="mode-virement-opt"><input type="radio" name="gravite_signalement" value="grave"> Grave</label>
                </div>
            </div>
            <div class="form-group">
                <textarea id="motif_signalement_user" rows="4" maxlength="500" placeholder="Décris ce qui s'est passé..." required></textarea>
            </div>
            <div class="form-group">
                <label style="font-size:0.85rem;">Preuve (optionnel)</label>
                <div class="upload-zone" onclick="document.getElementById('preuve_signalement').click()">
                    <i class="fas fa-camera"></i>
                    <p id="preuveLabel"><strong>Cliquez</strong> pour joindre une preuve</p>
                    <input type="file" id="preuve_signalement" accept="image/jpeg,image/png,image/webp" style="display:none;">
                </div>
            </div>
            <button type="submit" class="btn-primary-full" id="btnEnvoyerSignalementUser">
                <i class="fas fa-paper-plane"></i> Envoyer le signalement
            </button>
        </form>
    </div>
</div>

    <script src="<?php echo $base_path; ?>js/script.js"></script>
    <script>
    const BASE      = '<?php echo $base_path; ?>php/';
    const DEST_ID   = <?php echo (int)$selected_user; ?>;
    const DEST_INIT = '<?php echo $dest_init_val; ?>';
    const MY_INIT   = '<?php echo $my_init_val; ?>';
    const HAS_CONV  = <?php echo $has_conv_val; ?>;

    function ouvrirNavPanel() {
        document.getElementById('navPanel').classList.add('open');
        document.getElementById('navOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function fermerNavPanel() {
        document.getElementById('navPanel').classList.remove('open');
        document.getElementById('navOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') fermerNavPanel(); });

    let lastId = 0, upFile = null, upType = null;
    let isRec = false, recorder = null, recChunks = [], recSec = 0, recTmr = null;

    /* EMOJIS */
    const ECATS = {
        '😀':['😀','😂','🤣','😊','😍','🥰','😘','😎','🤩','😏','😒','😢','😭','😡','🥺','😴','🤔','🤗','😬','🙄'],
        '👍':['👍','👎','👏','🙌','🤝','💪','🤙','👌','✌️','🤞','🤟','☝️','👆','👇','👈','👉','🖐','✋'],
        '❤️':['❤️','🧡','💛','💚','💙','💜','🖤','💔','💕','💞','💓','💗','💖','💘','💝','❣️','✨'],
        '🎉':['🎉','🎊','🎈','🎁','🏆','🥇','🎯','🎮','🎲','🎸','🎵','🎤','🎬','📸','📱','💻','⚽','🏀'],
        '🐶':['🐶','🐱','🐰','🦊','🐻','🐼','🐯','🦁','🐮','🐷','🐸','🐵','🐔','🦆','🦅','🐠','🦋'],
        '🍕':['🍕','🍔','🌮','🍜','🍱','🍣','🍦','🎂','🍩','☕','🧃','🍺','🍷','🥤','🍹'],
    };

    function buildEP() {
        const cats = document.getElementById('epCats');
        if (!cats) return;
        const keys = Object.keys(ECATS);
        cats.innerHTML = keys.map((k,i)=>`<button class="ep-cat ${i===0?'on':''}" onclick="showCat('${k}',this)">${k}</button>`).join('');
        showCatKey(keys[0]);
    }
    function showCat(k,btn) {
        document.querySelectorAll('.ep-cat').forEach(b=>b.classList.remove('on'));
        btn.classList.add('on');
        showCatKey(k);
    }
    function showCatKey(k) {
        const grid = document.getElementById('epGrid');
        if (grid) grid.innerHTML = ECATS[k].map(e=>`<button class="ep-emoji" onclick="insEmoji('${e}')">${e}</button>`).join('');
    }
    function insEmoji(e) {
        const t = document.getElementById('msgInput');
        if (!t) return;
        const p = t.selectionStart;
        t.value = t.value.slice(0,p)+e+t.value.slice(p);
        t.focus(); t.selectionStart = t.selectionEnd = p+e.length;
        majSendBtn();
    }
    function toggleEP() {
        const box = document.getElementById('epBox');
        if (box) box.classList.toggle('show');
    }
    document.addEventListener('click', e=>{
        if (!e.target.closest('.emoji-picker-wrap')) {
            const box = document.getElementById('epBox');
            if (box) box.classList.remove('show');
        }
    });

    /* UPLOAD */
    function prevUp(inp, type) {
        const f = inp.files[0];
        if (!f) return;
        upFile = f; upType = type;
        const thumb = document.getElementById('upThumb');
        if (type==='image') { const u = URL.createObjectURL(f); thumb.innerHTML = '<img src="'+u+'">'; }
        else if (type==='video') { const u = URL.createObjectURL(f); thumb.innerHTML = '<video src="'+u+'" muted></video>'; }
        else { thumb.innerHTML = '<div class="thumb-icon">📎</div>'; }
        document.getElementById('upName').textContent = f.name;
        document.getElementById('upSize').textContent = fmtBytes(f.size);
        document.getElementById('upPrev').classList.add('show');
        document.getElementById('captionInp').classList.add('show');
        majSendBtn();
    }

    function annulerUp() {
        upFile = null; upType = null;
        const prev = document.getElementById('upPrev'); if (prev) prev.classList.remove('show');
        const cap  = document.getElementById('captionInp'); if (cap) { cap.classList.remove('show'); cap.value = ''; }
        const thumb= document.getElementById('upThumb'); if (thumb) thumb.innerHTML = '';
        ['inPhoto','inVideo','inFile'].forEach(id=>{ const el = document.getElementById(id); if (el) el.value=''; });
        majSendBtn();
    }

    function fmtBytes(b) {
        if(b<1024) return b+' o';
        if(b<1048576) return (b/1024).toFixed(1)+' Ko';
        return (b/1048576).toFixed(1)+' Mo';
    }

    /* VOCAL */
    async function toggleRec() {
        const btn = document.getElementById('recBtn');
        const tmr = document.getElementById('recTimer');
        if (!isRec) {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({audio:true});
                recorder = new MediaRecorder(stream);
                recChunks = []; recSec = 0;
                recorder.ondataavailable = e => recChunks.push(e.data);
                recorder.onstop = () => {
                    const blob = new Blob(recChunks, {type:'audio/webm'});
                    upFile = new File([blob], 'vocal_'+Date.now()+'.webm', {type:'audio/webm'});
                    upType = 'audio';
                    const u = URL.createObjectURL(blob);
                    document.getElementById('upThumb').innerHTML = '<audio src="'+u+'" controls style="width:160px;height:34px;"></audio>';
                    document.getElementById('upName').textContent = 'Message vocal';
                    document.getElementById('upSize').textContent = fmtBytes(blob.size);
                    document.getElementById('upPrev').classList.add('show');
                    stream.getTracks().forEach(t=>t.stop());
                    majSendBtn();
                };
                recorder.start();
                isRec = true;
                btn.classList.add('recording');
                tmr.classList.add('show');
                recTmr = setInterval(()=>{
                    recSec++;
                    const m=Math.floor(recSec/60), s=recSec%60;
                    tmr.textContent=m+':'+s.toString().padStart(2,'0');
                },1000);
            } catch(e) { alert('Microphone non disponible.'); }
        } else {
            recorder.stop();
            isRec = false;
            btn.classList.remove('recording');
            tmr.classList.remove('show');
            clearInterval(recTmr);
        }
    }

    /* ENVOI */
    function handleKey(e) {
        if (e.key==='Enter' && !e.shiftKey) { e.preventDefault(); envoyer(); }
    }
    function majSendBtn() {
        const inp = document.getElementById('msgInput');
        if (!inp) return;
        const val = inp.value.trim();
        const btn = document.getElementById('sendBtn');
        if (btn) btn.disabled = !val && !upFile;
    }
    async function envoyer() {
        if (!HAS_CONV) return;
        const inp     = document.getElementById('msgInput');
        const caption = document.getElementById('captionInp');
        const texte   = inp.value.trim();
        const cap     = caption.value.trim();
        if (!texte && !upFile) return;
        const btn = document.getElementById('sendBtn');
        btn.disabled = true;
        const fd = new FormData();
        fd.append('destinataire_id', DEST_ID);
        fd.append('message', upFile ? cap : texte);
        if (upFile) fd.append('media', upFile, upFile.name);
        try {
            const r = await fetch(BASE+'send_message.php', {method:'POST', body:fd});
            const d = await r.json();
            if (d.success) {
                inp.value = '';
                inp.style.height = 'auto';
                annulerUp();
                await charger(true);
            } else {
                alert(d.error || 'Erreur envoi');
                btn.disabled = false;
            }
        } catch(e) {
            alert('Erreur réseau');
            btn.disabled = false;
        }
    }

    /* CHARGEMENT MESSAGES */
    async function charger(force) {
        if (!HAS_CONV) return;
        try {
            const url = BASE+'get_messages.php?user_id='+DEST_ID+(lastId>0&&!force?'&since_id='+lastId:'');
            const r   = await fetch(url);
            const msgs = await r.json();
            if (msgs.error) return;
            if (!msgs.length && lastId===0) { vide(); return; }
            if (!msgs.length) return;
            if (force || lastId===0) { afficherTout(msgs); }
            else {
                const area = document.getElementById('msgsArea');
                msgs.filter(m=>m.id>lastId).forEach(m=>area.appendChild(bulle(m,null)));
                area.scrollTop = area.scrollHeight;
            }
            lastId = Math.max.apply(null, msgs.map(m=>m.id));
        } catch(e) {}
    }

    function vide() {
        const area = document.getElementById('msgsArea');
        if (area) area.innerHTML =
            '<div style="text-align:center;color:#aaa;padding:40px;"><i class="fas fa-comment" style="font-size:2.5rem;color:#e8e8e8;display:block;margin-bottom:10px;"></i>Démarrez la conversation !</div>';
    }

    function afficherTout(msgs) {
        const area = document.getElementById('msgsArea');
        area.innerHTML = '';
        let lastDate = null;
        msgs.forEach(function(m, i) {
            const d = new Date(m.date_envoi);
            const ds = d.toLocaleDateString('fr-FR',{weekday:'long',day:'numeric',month:'long'});
            if (ds !== lastDate) {
                const sep = document.createElement('div');
                sep.className = 'date-sep';
                sep.innerHTML = '<span>'+ds+'</span>';
                area.appendChild(sep);
                lastDate = ds;
            }
            area.appendChild(bulle(m, msgs[i-1]||null));
        });
        area.scrollTop = area.scrollHeight;
        afficherBarreEpingles(msgs);
    }

    function bulle(m, prev) {
        const row = document.createElement('div');
        row.className = 'msg-row '+(m.est_moi?'sent':'received');
        row.dataset.id = m.id;

        const showAv = !m.est_moi && (!prev || prev.expediteur_id !== m.expediteur_id || prev.est_moi);

        let media = '';
        if (m.type==='image' && m.fichier_url)
            media = '<img src="../'+eh(m.fichier_url)+'" class="msg-img" onclick="ouvrirMM(\'image\',\'../'+eh(m.fichier_url)+'\')" alt="">';
        else if (m.type==='video' && m.fichier_url)
            media = '<video src="../'+eh(m.fichier_url)+'" class="msg-vid" controls></video>';
        else if (m.type==='audio' && m.fichier_url)
            media = '<audio src="../'+eh(m.fichier_url)+'" class="msg-aud" controls></audio>';
        else if (m.type==='fichier' && m.fichier_url)
            media = '<a href="../'+eh(m.fichier_url)+'" download="'+eh(m.fichier_nom||'fichier')+'" class="msg-file-link">' +
                    '<i class="fas fa-file-alt"></i>' +
                    '<div><div class="msg-file-name">'+eh(m.fichier_nom||'Fichier')+'</div>' +
                    '<div class="msg-file-size">'+(m.fichier_taille?fmtBytes(m.fichier_taille):'')+'</div></div></a>';

        const txt  = m.contenu ? '<div>'+eh(m.contenu)+'</div>' : '';
        const vu   = m.est_moi ? '<i class="fas fa-check-double" style="color:'+(m.lu?'#e94560':'#ccc')+';font-size:0.65rem;"></i>' : '';
        const reacts = (m.reactions||[]).length
            ? '<div class="msg-reactions">'+(m.reactions||[]).map(r=>'<span class="react-pill" title="'+eh(r.pseudo)+'">'+r.emoji+'</span>').join('')+'</div>'
            : '';
        const estEpingle = !!m.epingle_jusqu;

        row.innerHTML =
            (showAv ? '<div class="msg-mini-av">'+DEST_INIT+'</div>' : '<div style="width:26px;flex-shrink:0;"></div>') +
            '<div class="msg-bubble '+(estEpingle?'epinglee':'')+'">' +
                (estEpingle ? '<div class="msg-pin-tag"><i class="fas fa-thumbtack"></i></div>' : '') +
                '<button class="react-btn" onclick="openQE('+m.id+',this)">😊</button>' +
                '<button class="pin-btn" onclick="openPinMenu('+m.id+',this,'+estEpingle+')"><i class="fas fa-thumbtack"></i></button>' +
                '<div class="msg-inner">'+media+txt+'</div>' +
                reacts +
                '<div class="msg-time"><span>'+m.date_affichage+'</span>'+vu+'</div>' +
            '</div>';
        return row;
    }

    /* RÉACTIONS */
    const QE = ['❤️','😂','👍','😮','😢','😡'];
    function openQE(id, btn) {
        document.querySelectorAll('.quick-emojis').forEach(p=>p.remove());
        const p = document.createElement('div');
        p.className = 'quick-emojis';
        p.innerHTML = QE.map(e=>'<span onclick="reagir('+id+',\''+e+'\')">'+e+'</span>').join('');
        btn.closest('.msg-bubble').appendChild(p);
        setTimeout(()=>document.addEventListener('click',()=>p.remove(),{once:true}),100);
    }
    async function reagir(id, emoji) {
        document.querySelectorAll('.quick-emojis').forEach(p=>p.remove());
        try {
            await fetch(BASE+'reaction_message.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'message_id='+id+'&emoji='+encodeURIComponent(emoji)
            });
            await charger(true);
        } catch(e) {}
    }

    /* ÉPINGLAGE */
    function openPinMenu(id, btn, dejaEpingle) {
        document.querySelectorAll('.pin-menu').forEach(p=>p.remove());
        const p = document.createElement('div');
        p.className = 'pin-menu';
        let html = '';
        html += '<span onclick="epinglerMessage('+id+',\'24h\')"><i class="fas fa-thumbtack"></i> 24 heures</span>';
        html += '<span onclick="epinglerMessage('+id+',\'7j\')"><i class="fas fa-thumbtack"></i> 7 jours</span>';
        html += '<span onclick="epinglerMessage('+id+',\'1mois\')"><i class="fas fa-thumbtack"></i> 1 mois</span>';
        if (dejaEpingle) html += '<span onclick="epinglerMessage('+id+',\'annuler\')" class="pin-annuler"><i class="fas fa-thumbtack"></i> Retirer l\'épingle</span>';
        p.innerHTML = html;
        btn.closest('.msg-bubble').appendChild(p);
        setTimeout(()=>document.addEventListener('click',()=>p.remove(),{once:true}),100);
    }
    async function epinglerMessage(id, duree) {
        document.querySelectorAll('.pin-menu').forEach(p=>p.remove());
        try {
            await fetch(BASE+'epingler_message.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'message_id='+id+'&duree='+duree
            });
            showToast(duree === 'annuler' ? 'Épingle retirée' : 'Message épinglé', 'success');
            await charger(true);
        } catch(e) {}
    }
    function afficherBarreEpingles(msgs) {
        const epingles = (msgs||[]).filter(m => m.epingle_jusqu);
        let barre = document.getElementById('barreEpingles');
        if (!epingles.length) { if (barre) barre.remove(); return; }
        if (!barre) {
            barre = document.createElement('div');
            barre.id = 'barreEpingles';
            barre.className = 'barre-epingles';
            const area = document.querySelector('.msgs-area');
            if (area) area.insertAdjacentElement('beforebegin', barre);
        }
        barre.innerHTML = epingles.map(function(m){
            const c = (m.contenu||'').slice(0,60);
            return '<div class="epingle-item" onclick="scrollVers('+m.id+')"><i class="fas fa-thumbtack"></i><span>'+eh(c)+((m.contenu||'').length>60?'…':'')+'</span></div>';
        }).join('');
    }
    function scrollVers(id) {
        const el = document.querySelector('[data-id="'+id+'"]');
        if (el) el.scrollIntoView({behavior:'smooth',block:'center'});
    }

    /* MODAL MÉDIA */
    function ouvrirMM(type, url) {
        document.getElementById('mmContent').innerHTML = type==='image'
            ? '<img src="'+url+'" alt="">'
            : '<video src="'+url+'" controls autoplay style="max-width:92vw;max-height:90vh;"></video>';
        document.getElementById('mediaModal').classList.add('open');
    }
    function fermerMM() {
        document.getElementById('mediaModal').classList.remove('open');
        document.getElementById('mmContent').innerHTML='';
    }
    document.addEventListener('keydown',e=>{ if(e.key==='Escape') fermerMM(); });

    /* UTILITAIRES */
    function autoGrow(el) {
        el.style.height='auto';
        el.style.height=Math.min(el.scrollHeight,110)+'px';
    }
    function eh(s) {
        const d=document.createElement('div');
        d.textContent=String(s||'');
        return d.innerHTML;
    }
    function filtrerConv(q) {
        document.querySelectorAll('.conv-item').forEach(i=>{
            i.style.display = (i.dataset.nom||'').indexOf(q.toLowerCase()) !== -1 ? '' : 'none';
        });
    }

    /* SIGNALEMENT */
    let signalementCible = null;
    function ouvrirSignalementUtilisateur(userId, contexte, articleId) {
        signalementCible = { userId: userId, contexte: contexte, articleId: articleId };
        document.getElementById('modalSignalementUser').classList.add('open');
    }
    function fermerSignalementUtilisateur() {
        document.getElementById('modalSignalementUser').classList.remove('open');
    }
    var preuveEl = document.getElementById('preuve_signalement');
    if (preuveEl) {
        preuveEl.addEventListener('change', function() {
            const lbl = document.getElementById('preuveLabel');
            if (lbl && this.files[0]) lbl.innerHTML = '<strong>' + eh(this.files[0].name) + '</strong>';
        });
    }
    var formSig = document.getElementById('formSignalementUser');
    if (formSig) {
        formSig.addEventListener('submit', function(e) {
            e.preventDefault();
            const motif = document.getElementById('motif_signalement_user').value.trim();
            if (motif.length < 5 || !signalementCible) return;
            const btn = document.getElementById('btnEnvoyerSignalementUser');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
            const fd = new FormData();
            fd.append('utilisateur_signale_id', signalementCible.userId);
            fd.append('motif', motif);
            fd.append('contexte', signalementCible.contexte);
            fd.append('article_id', signalementCible.articleId || '');
            const g = document.querySelector('input[name="gravite_signalement"]:checked');
            fd.append('gravite', g ? g.value : 'moyen');
            const fichierPreuve = document.getElementById('preuve_signalement').files[0];
            if (fichierPreuve) fd.append('preuve', fichierPreuve);
            fetch(BASE + 'signaler_utilisateur.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer le signalement';
                if (d.require_login) { window.location.href = BASE + 'login.php'; return; }
                if (!d.success) { showToast(d.error || 'Erreur', 'error'); return; }
                showToast('Signalement envoyé, merci.', 'success');
                fermerSignalementUtilisateur();
            });
        });
    }

    /* INIT */
    buildEP();
    majSendBtn();
    if (HAS_CONV) {
        charger(true);
        setInterval(function(){ charger(false); }, 4000);
    }
    </script>
</body>
</html>