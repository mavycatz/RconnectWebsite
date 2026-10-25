<?php
declare(strict_types = 1);

require_once __DIR__ . '/../../../app/Core/Bootstrap.php';
require_once __DIR__ . '/../../Controllers/Announcement/AnnouncementController.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;
use Admin\Controllers\Announcement\AnnouncementController;

Session::start();

if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

$user = Auth::user();
if (!in_array($user['role'] ?? null, ['admin', 'super_admin'])) {
    header('Location: /admin/');
    exit;
}

$db = Database::get();

$getSetting = function (string $key, string $default = '') use ($db): string {
    try {
        $s = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $s->execute([$key]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r ? $r['setting_value'] : $default;
    } catch (\Throwable $e) {
        return $default;
    }
};

$fbPageId = $getSetting('facebook_page_id', '');
$fbToken = $getSetting('facebook_page_access_token', '');

$currentUser = $user;
$stmt2 = $db->prepare("SELECT profile_photo FROM users WHERE id = ?");
$stmt2->execute([(int)($currentUser['id'] ?? 0)]);
$ppRow2 = $stmt2->fetch(PDO::FETCH_ASSOC);
$pp = !empty($ppRow2['profile_photo']) ? '/uploads/' . $ppRow2['profile_photo'] : null;
$avatarInitial = strtoupper(substr($currentUser['username'] ?? 'A', 0, 1));

include __DIR__ . '/../dashboard/layout/_header.php';
include __DIR__ . '/../dashboard/layout/_style.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
    @media(max-width:991px) {
        #mainContent { padding: 10px!important; margin-top: 60px!important }
        #mainContent>div[style*="padding:24px"] { padding: 14px!important; border-radius: 14px!important }
        #mainContent .card { border-radius: 14px!important }
        #mainContent .card-body { padding: 12px!important }
    }
    * { font-family: 'Poppins', sans-serif }
    body { background: #eceed9 }

    .ann-feed { max-width: 720px; margin: 0 auto; display: flex; flex-direction: column; gap: 16px }
    .feed-day-divider { display: flex; align-items: center; gap: 12px; margin: 6px 2px 0 }
    .feed-day-divider::before, .feed-day-divider::after { content: ''; flex: 1; height: 1px; background: #e5e0a4 }
    .feed-day-divider span { font-size: .7rem; font-weight: 700; color: #8a9a8a; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap }

    /* ---- Page header ---- */
    .ann-summary { display: inline-flex; align-items: center; gap: 6px; background: #f4f1e6; border: 1px solid #e5e0a4; border-radius: 20px; padding: 7px 14px; font-size: .72rem; font-weight: 600; color: #6b7a63 }
    .ann-filter-summary { display: inline-flex; align-items: center; gap: 7px; margin-top: 8px; background: #fff; border: 1px solid #ece7d2; color: #475569; border-radius: 20px; padding: 4px 12px; font-size: .7rem; font-weight: 600; box-shadow: 0 1px 3px rgba(41,87,56,.05) }
    .ann-filter-summary i { color: #295738; font-size: .74rem }

    /* ---- Category tabs ---- */
    .ann-tabs { display: flex; gap: 8px; margin: 0 0 14px; flex-wrap: wrap }
    .ann-tab { display: inline-flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #e5e0a4; border-radius: 20px; padding: 8px 18px; font-size: .8rem; font-weight: 700; color: #6f6d75; cursor: pointer; transition: all .2s }
    .ann-tab:hover { border-color: #295738; color: #295738 }
    .ann-tab.active { background: #295738; border-color: #295738; color: #fff }
    .ann-tab.active i { color: #fff }
    .ann-tab-count { display: inline-flex; min-width: 20px; justify-content: center; background: rgba(0,0,0,.08); border-radius: 10px; padding: 1px 7px; font-size: .68rem }
    .ann-tab.active .ann-tab-count { background: rgba(255,255,255,.22) }

    /* ---- Composer ---- */
    .ann-composer { max-width: 720px; margin: 0 auto 14px; background: #fff; border: 1px solid #e5e0a4; border-radius: 18px; padding: 14px 16px; box-shadow: 0 2px 10px rgba(41,87,56,.05) }
    .ann-composer-head { display: flex; align-items: center; gap: 12px; cursor: pointer }
    .ann-composer-fake { flex: 1; background: #f6f3e9; border: 1px solid #ece7d2; border-radius: 22px; padding: 10px 16px; font-size: .82rem; color: #94a3b8 }
    .ann-composer-head:hover .ann-composer-fake { border-color: #cdd9b5; background: #fbfaf3 }
    .ann-posting-as { display: flex; align-items: center; gap: 6px; font-size: .72rem; color: #8a9a8a; font-weight: 600; margin: 1px 0 0 }
    .ann-composer-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #cdd9b5; flex-shrink: 0 }
    .ann-field-label { display: block; font-size: .68rem; font-weight: 700; color: #8a9a8a; text-transform: uppercase; letter-spacing: .05em; margin: 12px 0 0; }
    .ann-composer-form input[type="text"], .ann-composer-form textarea { width: 100%; border: none; border-bottom: 1px solid #f0ede0; background: transparent; padding: 10px 2px; font-size: .9rem; color: #1e293b; outline: none; resize: none }
    .ann-composer-form input[type="text"] { font-weight: 600; font-size: .95rem }
    .ann-composer-form textarea { min-height: 66px; font-size: .85rem; color: #475569 }
    .ann-composer-tools { display: flex; align-items: center; gap: 12px; flex-wrap: wrap }
    .ann-composer-actions { display: flex; gap: 8px }
    .ann-btn { display: inline-flex; align-items: center; gap: 6px; border-radius: 20px; font-size: .78rem; font-weight: 700; padding: 8px 20px; cursor: pointer; transition: all .2s; border: 1px solid transparent }
    .ann-btn-primary { background: #295738; color: #fff }
    .ann-btn-primary:hover { background: #1e4229 }
    .ann-btn-ghost { background: transparent; border-color: #d8d2b6; color: #295738 }
    .ann-btn-ghost:hover { background: #f4f1e6 }
    .ann-photo-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; border: 1px solid #cdd9b5; font-size: .75rem; font-weight: 600; color: #295738; cursor: pointer; transition: all .2s; background: #fef8f3; margin: 0 }
    .ann-photo-btn:hover { background: #e8f0e0 }

    /* ---- Toolbar ---- */
    .ann-toolbar { max-width: 720px; margin: 0 auto 14px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap }
    .ann-date-chip { display: inline-flex; align-items: center; gap: 6px; background: #e8f2e6; border: 1px solid #cdd9b5; color: #295738; border-radius: 20px; padding: 7px 14px; font-size: .72rem; font-weight: 700; cursor: pointer; white-space: nowrap }
    .ann-fb-select { border: 1px solid #e5e0a4; border-radius: 20px; padding: 7px 12px; font-size: .72rem; font-weight: 600; color: #475569; background: #fff; outline: none }

    /* ---- Date FAB ---- */
    .ann-fab-wrap { position: fixed; right: 24px; bottom: 24px; z-index: 1055; display: flex; flex-direction: column; align-items: flex-end }
    .ann-fab { display: inline-flex; align-items: center; gap: 8px; height: 52px; padding: 0 18px; border-radius: 26px; background: linear-gradient(135deg,#2f6b45,#295738); color: #fff; border: 1px solid rgba(255,255,255,.35); font-size: .82rem; font-weight: 700; cursor: pointer; box-shadow: 0 12px 28px rgba(41,87,56,.35), inset 0 1px 0 rgba(255,255,255,.25); transition: transform .2s, box-shadow .2s }
    .ann-fab:hover { transform: translateY(-2px); box-shadow: 0 16px 32px rgba(41,87,56,.42), inset 0 1px 0 rgba(255,255,255,.25) }
    .ann-fab.active { border-color: #f4c542; box-shadow: 0 12px 28px rgba(41,87,56,.35), 0 0 0 3px rgba(244,197,66,.28) }
    .ann-fab > i:first-child { font-size: 1.05rem }
    .ann-fab-chevron { font-size: .7rem; opacity: .85; transition: transform .2s }
    .ann-fab.open .ann-fab-chevron { transform: rotate(180deg) }
    .ann-fab-panel { width: 288px; max-width: calc(100vw - 32px); background: #fff; border: 1px solid #e5e0a4; border-radius: 18px; box-shadow: 0 20px 48px rgba(41,87,56,.22); padding: 10px; margin-bottom: 12px; animation: annFabIn .18s ease }
    @keyframes annFabIn { from { opacity: 0; transform: translateY(6px) scale(.98) } to { opacity: 1; transform: none } }
    .ann-fab-head { display: flex; align-items: center; justify-content: space-between; padding: 4px 6px 8px }
    .ann-fab-head-title { display: inline-flex; align-items: center; gap: 7px; font-size: .7rem; font-weight: 700; color: #295738; text-transform: uppercase; letter-spacing: .05em }
    .ann-fab-close { background: transparent; border: none; color: #94a3b8; font-size: .72rem; cursor: pointer; padding: 3px 6px; border-radius: 8px }
    .ann-fab-close:hover { background: #f4f1e6; color: #295738 }
    .ann-fab-opt { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; background: transparent; border: none; border-radius: 12px; padding: 9px 10px; font-size: .8rem; font-weight: 600; color: #475569; cursor: pointer; transition: background .15s }
    .ann-fab-opt > i:first-child { color: #8a9a8a; width: 16px; text-align: center; font-size: .85rem }
    .ann-fab-opt:hover { background: #f6f3e9 }
    .ann-fab-opt.active { background: #e8f2e6; color: #295738 }
    .ann-fab-opt.active > i:first-child { color: #295738 }
    .ann-fab-check { margin-left: auto; opacity: 0; color: #295738; font-size: .85rem }
    .ann-fab-opt.active .ann-fab-check { opacity: 1 }
    .ann-fab-sep { height: 1px; background: #f0ede0; margin: 8px 6px }
    .ann-fab-label { display: block; font-size: .66rem; font-weight: 700; color: #8a9a8a; text-transform: uppercase; letter-spacing: .05em; margin: 0 6px 6px }
    .ann-fab-custom { padding: 0 6px }
    .ann-fab-dates { display: flex; align-items: center; gap: 6px }
    .ann-fab-dates input { flex: 1; min-width: 0; border: 1px solid #e5e0a4; border-radius: 10px; padding: 6px 8px; font-size: .72rem; color: #475569; background: #fefdf8 }
    .ann-fab-dates input:focus { outline: none; border-color: #295738; box-shadow: 0 0 0 2px rgba(41,87,56,.12) }
    .ann-fab-dates span { font-size: .68rem; color: #94a3b8 }
    .ann-fab-apply { width: 100%; margin-top: 8px; border: none; background: #295738; color: #fff; border-radius: 10px; padding: 8px 12px; font-size: .75rem; font-weight: 700; cursor: pointer; transition: background .2s }
    .ann-fab-apply:hover { background: #1e4229 }
    .ann-fab-reset { display: flex; align-items: center; gap: 6px; margin: 8px 6px 2px; background: transparent; border: none; color: #c62828; font-size: .74rem; font-weight: 700; cursor: pointer; padding: 4px 0 }
    .ann-fab-reset:hover { text-decoration: underline }

    /* ---- Cards ---- */
    .fb-post { background: #fff; border: 1px solid #e5e0a4; border-radius: 18px; overflow: hidden; transition: transform .25s cubic-bezier(.4, 0, .2, 1), box-shadow .25s cubic-bezier(.4, 0, .2, 1); display: flex; flex-direction: column; position: relative }
    .fb-post:hover { transform: translateY(-3px); box-shadow: 0 12px 35px rgba(41, 87, 56, .12) }
    .fb-post::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #295738, #5a9e6f); opacity: 0; transition: opacity .25s }
    .fb-post:hover::after { opacity: 1 }

    .fb-avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #295738, #4a8c5e); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; box-shadow: 0 2px 8px rgba(41, 87, 56, .2) }
    .fb-image { width: 100%; max-height: 360px; object-fit: cover; border-radius: 14px; border: 1px solid #e5e0a4; display: block }
    .fb-body { padding: 16px 18px 14px; flex: 1; display: flex; flex-direction: column }
    .fb-content { font-size: .82rem; color: #475569; line-height: 1.65; white-space: pre-wrap; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; cursor: pointer }
    .fb-content.expanded { -webkit-line-clamp: unset; display: block }
    .fb-actions { display: flex; align-items: center; gap: 6px; border-top: 1px solid #f0ede0; padding-top: 10px; margin-top: auto; flex-wrap: wrap }
    .fb-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 600 }
    .fb-badge-success { background: #e8f5e9; color: #2e7d32 }
    .fb-badge-danger { background: #fde8e8; color: #c62828 }
    .fb-badge-team { background: #f6efdc; color: #8a6d1f }
    .fb-audience { font-size: .7rem; color: #8a9a8a; font-weight: 600; margin: 0 0 8px }
    .fb-audience i { color: #8a6d1f; margin-right: 4px }
    .fb-repost-badge { background: #e3f2fd; color: #1565c0 }
    .fb-role { font-size: 10px; font-weight: 700; color: #6b7a63; background: #f4f1e6; border-radius: 10px; padding: 2px 8px }
    .fb-time { font-size: 10px; color: #94a3b8; white-space: nowrap }
    .fb-action { display: inline-flex; align-items: center; gap: 5px; background: transparent; border: 1px solid #d8d2b6; border-radius: 20px; padding: 4px 12px; font-size: .7rem; font-weight: 600; color: #295738; cursor: pointer; transition: all .2s }
    .fb-action:hover { background: #295738; border-color: #295738; color: #fff }
    .fb-action-danger { border-color: #e74c3c; color: #e74c3c }
    .fb-action-danger:hover { background: #e74c3c; border-color: #e74c3c; color: #fff }

    .ann-audience-toggle { display: inline-flex; background: #f4f1e6; border: 1px solid #e5e0a4; border-radius: 20px; padding: 3px; gap: 3px }
    .ann-audience-option { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 18px; font-size: .75rem; font-weight: 600; color: #6f6d75; cursor: pointer; transition: all .2s; margin: 0 }
    .ann-audience-option.active { background: #295738; color: #fff }
    .ann-audience-option input { display: none }
    #annAudienceRoles { display: none; flex-wrap: wrap; gap: 6px; padding-top: 10px }
    .ann-role-check { display: inline-flex; align-items: center; gap: 6px; background: #fef8f3; border: 1px solid #cdd9b5; border-radius: 20px; padding: 4px 12px; font-size: .72rem; font-weight: 600; color: #295738; cursor: pointer; margin: 0 }
    .ann-role-check input { accent-color: #295738; margin: 0 }

    .empty-state { padding: 54px 20px; text-align: center }
    .empty-state > i { font-size: 3rem; color: #cdd9b5; display: block; margin-bottom: 16px }
    .empty-state h6 { font-weight: 600; color: #295738; margin: 0 0 4px }
    .empty-state p { font-size: .85rem; color: #94a3b8; margin: 0 }

    .ann-detail-modal { border: 1px solid #e5e0a4; border-radius: 20px; background: #faf6ed; overflow: hidden }
    .ann-detail-bar { border-bottom: 1px solid #efe8d4; background: #fffdf7; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between }
    .ann-back-btn { display: inline-flex; align-items: center; gap: 8px; background: transparent; border: 1px solid #d8d2b6; color: #295738; font-size: .78rem; font-weight: 600; border-radius: 20px; padding: 7px 14px; cursor: pointer; transition: all .2s }
    .ann-back-btn:hover { background: #295738; border-color: #295738; color: #fff }
    .ann-detail-body { background: #faf6ed; padding: 0 }
    .ann-detail-wrap { max-width: 760px; margin: 0 auto; padding: 34px 28px 40px }

    .ann-cat-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 20px; background: #e8f2e6; color: #295738; font-size: .7rem; font-weight: 700; letter-spacing: .02em; text-transform: uppercase }
    .ann-detail-title { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.7rem; line-height: 1.25; color: #24513c; margin: 14px 0 10px; letter-spacing: -.01em }
    .ann-meta-row { display: flex; align-items: center; flex-wrap: wrap; gap: 8px 14px; color: #8a9a8a; font-size: .8rem; font-weight: 500; padding-bottom: 18px; margin-bottom: 22px; border-bottom: 1px solid #efe8d4 }
    .ann-meta-dot { color: #cdd9b5 }
    .ann-author { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #efe8d4; border-radius: 14px; padding: 12px 16px; margin-bottom: 22px }
    .ann-avatar { width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #295738, #4a8c5e); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; flex-shrink: 0; box-shadow: 0 2px 8px rgba(41, 87, 56, .18) }
    .ann-author-name { font-weight: 700; color: #295738; font-size: .85rem }
    .ann-author-posted { font-size: .72rem; color: #94a3b8 }
    .ann-hero { width: 100%; aspect-ratio: 16/9; max-height: 420px; object-fit: cover; border-radius: 16px; border: 1px solid #e5e0a4; box-shadow: 0 4px 16px rgba(41, 87, 56, .08); display: block; margin-bottom: 26px; background: #fff }
    .ann-hero-placeholder { width: 100%; aspect-ratio: 16/9; border-radius: 16px; border: 1px dashed #cdd9b5; background: linear-gradient(135deg, #f4f0df, #e9f0e2); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; margin-bottom: 26px }
    .ann-hero-placeholder i { font-size: 3rem; color: #aec6a6 }
    .ann-hero-placeholder span { font-size: .78rem; color: #8a9a8a; font-weight: 600 }
    .ann-content { font-size: .95rem; color: #3f4a3f; line-height: 1.85; white-space: pre-wrap; word-wrap: break-word }
    .ann-highlight { margin-top: 26px; background: #f0f7ec; border: 1px solid #cdd9b5; border-left: 4px solid #295738; border-radius: 12px; padding: 16px 18px }
    .ann-highlight-title { display: flex; align-items: center; gap: 8px; font-weight: 700; color: #295738; font-size: .82rem; margin-bottom: 8px }
    .ann-highlight-text { font-size: .85rem; color: #3f4a3f; line-height: 1.7; white-space: pre-wrap }
    .ann-detail-footer { margin-top: 32px; padding-top: 18px; border-top: 1px solid #efe8d4; display: flex; flex-wrap: wrap; gap: 10px; font-size: .74rem; color: #8a9a8a }
    .ann-foot-chip { display: inline-flex; align-items: center; gap: 6px; background: #fff; border: 1px solid #efe8d4; border-radius: 20px; padding: 6px 12px }
    .ann-foot-chip i { color: #5a9e6f }

    @media(max-width:768px) {
        .ann-detail-wrap { padding: 22px 16px 28px }
        .ann-detail-title { font-size: 1.25rem }
        .ann-hero, .ann-hero-placeholder { aspect-ratio: 4/3; max-height: 280px }
        .ann-back-btn { font-size: .72rem; padding: 6px 12px }
        .ann-fab-wrap { right: 14px; bottom: 16px }
    }
    @media(max-width:480px) {
        .ann-fab { padding: 0; width: 52px; justify-content: center; gap: 0 }
        .ann-fab-label-text, .ann-fab-chevron { display: none }
    }
</style>
</head>
<body>

<?php include __DIR__ . '/../../assets/layout/navbaradmin.php'; ?>

<div class="main-content" id="mainContent">
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">
        <div class="container-fluid p-0">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <div>
                    <h1 style="font-weight:800; font-size:1.4rem; color:#295738; margin:0 0 2px;">
                        Announcements
                    </h1>
                    <p style="font-weight:500; font-size:0.82rem; color:#8a9a8a; margin:0;">
                        Keep everyone informed with the latest updates.
                    </p>
                    <span class="ann-filter-summary" id="annFilterSummary"></span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="ann-summary" id="annSummary">&mdash;</span>
                    <button class="btn btn-sm fw-semibold" style="background:transparent;border:1px solid #b5ad55;color:#295738;border-radius:20px;padding:7px 18px;height:38px;transition:all .2s;" onmouseover="this.style.background='#295738';this.style.color='#fff'" onmouseout="this.style.background='transparent';this.style.color='#295738'" data-bs-toggle="modal" data-bs-target="#fbSettingsModal">
                        <i class="bi bi-facebook"></i> Facebook
                    </button>
                </div>
            </div>

            <div class="ann-tabs" id="annTabs">
                <button type="button" class="ann-tab active" data-category="public" onclick="setCategory('public')">
                    <i class="bi bi-globe2"></i> Public Updates <span class="ann-tab-count" id="tabCountPublic">0</span>
                </button>
                <button type="button" class="ann-tab" data-category="team" onclick="setCategory('team')">
                    <i class="bi bi-people-fill"></i> Team Feed <span class="ann-tab-count" id="tabCountTeam">0</span>
                </button>
            </div>

            <div class="ann-composer" id="annComposer">
                <div class="ann-composer-head" id="annComposerTrigger" onclick="expandComposer()">
                    <?php if ($pp): ?>
                        <img src="<?= htmlspecialchars($pp) ?>?t=<?= time() ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #cdd9b5;flex-shrink:0;">
                    <?php else: ?>
                        <div class="fb-avatar" style="width:40px;height:40px;font-size:16px;"><?= htmlspecialchars($avatarInitial) ?></div>
                    <?php endif; ?>
                    <div class="ann-composer-fake">Write an announcement...</div>
                </div>
            </div>

            <div class="ann-toolbar">
                <div style="position:relative;flex:1;max-width:280px;min-width:170px;">
                    <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.8rem;"></i>
                    <input type="text" id="annSearch" class="form-control form-control-sm" placeholder="Search title, content, author, date..." title="Searches titles, content, author names, roles, categories, dates, and Facebook status" style="border-radius:20px;border:1px solid #e5e0a4;padding:7px 14px 7px 34px;font-size:.8rem;background:#fff;width:100%;">
                </div>
                <span class="ann-date-chip" id="annDateChip" onclick="toggleFab()" title="Filter by date">
                    <i class="bi bi-calendar3"></i> <span id="annDateChipLabel">Today</span>
                </span>
                <select id="annFbFilter" class="ann-fb-select" onchange="setFbFilter(this.value)">
                    <option value="any">Facebook: All</option>
                    <option value="posted">Facebook: Posted</option>
                    <option value="failed">Facebook: Failed</option>
                </select>
                <span id="annCount" style="font-size:.8rem;color:#94a3b8;margin-left:auto;">0 items</span>
            </div>

            <div id="announcementList">
                <div class="empty-state">
                    <div class="spinner-border" style="color:#295738;width:2rem;height:2rem;" role="status"></div>
                    <h6 style="margin-top:12px;">Loading announcements...</h6>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="ann-fab-wrap">
    <div class="ann-fab-panel" id="annFabPanel" style="display:none;">
        <div class="ann-fab-head">
            <span class="ann-fab-head-title"><i class="bi bi-calendar3"></i> Filter by date</span>
            <button type="button" class="ann-fab-close" onclick="closeFab()" aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <button type="button" class="ann-fab-opt" data-date="today" onclick="setDateFilter('today')"><i class="bi bi-calendar-day"></i> Today <i class="bi bi-check2 ann-fab-check"></i></button>
        <button type="button" class="ann-fab-opt" data-date="yesterday" onclick="setDateFilter('yesterday')"><i class="bi bi-calendar-minus"></i> Yesterday <i class="bi bi-check2 ann-fab-check"></i></button>
        <button type="button" class="ann-fab-opt" data-date="week" onclick="setDateFilter('week')"><i class="bi bi-calendar-week"></i> This Week <i class="bi bi-check2 ann-fab-check"></i></button>
        <button type="button" class="ann-fab-opt" data-date="month" onclick="setDateFilter('month')"><i class="bi bi-calendar-month"></i> This Month <i class="bi bi-check2 ann-fab-check"></i></button>
        <button type="button" class="ann-fab-opt" data-date="all" onclick="setDateFilter('all')"><i class="bi bi-calendar3"></i> All Dates <i class="bi bi-check2 ann-fab-check"></i></button>
        <div class="ann-fab-sep"></div>
        <div class="ann-fab-custom">
            <span class="ann-fab-label">Custom range</span>
            <div class="ann-fab-dates">
                <input type="date" id="annFrom">
                <span>to</span>
                <input type="date" id="annTo">
            </div>
            <button type="button" class="ann-fab-apply" onclick="applyCustomRange()"><i class="bi bi-funnel"></i> Apply range</button>
        </div>
        <button type="button" class="ann-fab-reset" onclick="resetDateFilter()"><i class="bi bi-arrow-counterclockwise"></i> Reset to Today</button>
    </div>
    <button type="button" class="ann-fab" id="annFabBtn" onclick="toggleFab()" title="Filter by date">
        <i class="bi bi-calendar3"></i>
        <span class="ann-fab-label-text" id="annFabLabel">Today</span>
        <i class="bi bi-chevron-up ann-fab-chevron"></i>
    </button>
</div>

<div class="modal fade" id="composerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:640px;">
        <div class="modal-content" style="border:1px solid #e5e0a4;border-radius:20px;background:#fff;overflow:hidden;">
            <div class="modal-header" style="border-bottom:1px solid #f0ede0;padding:14px 20px;background:#fef8f3;">
                <div class="d-flex align-items-center gap-2" style="min-width:0;">
                    <?php if ($pp): ?>
                        <img src="<?= htmlspecialchars($pp) ?>?t=<?= time() ?>" class="ann-composer-avatar" alt="">
                    <?php else: ?>
                        <div class="fb-avatar" style="width:36px;height:36px;font-size:14px;"><?= htmlspecialchars($avatarInitial) ?></div>
                    <?php endif; ?>
                    <div style="min-width:0;">
                        <h5 class="modal-title fw-bold" style="font-size:.95rem;color:#295738;" id="annComposerTitle">Create announcement</h5>
                        <div class="ann-posting-as"><i class="bi bi-person-check-fill"></i> Posting as <?= htmlspecialchars($currentUser['username'] ?? 'Admin') ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close" onclick="collapseComposer()" aria-label="Close"></button>
            </div>
            <form id="announcementForm" class="ann-composer-form" enctype="multipart/form-data">
                <div class="modal-body" style="padding:14px 20px 4px;">
                    <input type="hidden" name="id" id="annEditId" value="">

                    <label class="ann-field-label" for="annTitle">Title</label>
                    <input type="text" name="title" id="annTitle" required placeholder="What's the title of your announcement?">
                    <label class="ann-field-label" for="annMessage">Content</label>
                    <textarea name="message" id="annMessage" rows="4" required placeholder="Write something..."></textarea>

                    <div style="padding-top:2px;">
                        <label class="ann-field-label" style="margin-bottom:6px;">Audience <span id="annAudienceNote" style="text-transform:none;font-weight:600;color:#8a6d1f;display:none;">&middot; operational posts stay in the Team Feed</span></label>
                        <div class="ann-audience-toggle" id="annAudienceToggle">
                            <label class="ann-audience-option active" data-visibility="public">
                                <input type="radio" name="visibility" value="public" checked>
                                <i class="bi bi-globe2"></i> Public Update
                            </label>
                            <label class="ann-audience-option" data-visibility="private">
                                <input type="radio" name="visibility" value="private">
                                <i class="bi bi-people-fill"></i> Team Feed
                            </label>
                        </div>
                        <div id="annAudienceRoles">
                            <?php foreach (AnnouncementController::AUDIENCE_ROLES as $roleKey => $roleLabel): ?>
                            <label class="ann-role-check">
                                <input type="checkbox" name="target_roles[]" value="<?= htmlspecialchars($roleKey) ?>" checked>
                                <?= htmlspecialchars($roleLabel) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="annImagePreview" style="display:none;margin-top:10px;position:relative;">
                        <img id="annImagePreviewImg" style="width:100%;max-height:200px;object-fit:cover;border-radius:14px;border:1px solid #e5e0a4;">
                        <button type="button" onclick="clearAnnImage()" style="position:absolute;top:6px;right:6px;width:28px;height:28px;border-radius:50%;background:rgba(0,0,0,0.6);color:#fff;border:none;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;">&times;</button>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #f0ede0;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                    <div class="ann-composer-tools">
                        <label class="ann-photo-btn">
                            <i class="bi bi-image" style="font-size:.9rem;"></i> Add Photo
                            <input type="file" name="image" id="annImage" accept="image/*" style="display:none;" onchange="previewAnnImage(this)">
                        </label>
                        <span id="annRemoveImageWrap" style="display:none;">
                            <button type="button" class="ann-btn ann-btn-ghost" style="padding:6px 14px;" onclick="removeAnnImage()"><i class="bi bi-trash3"></i> Remove image</button>
                        </span>
                        <span style="display:inline-flex;align-items:center;gap:8px;">
                            <i class="bi bi-facebook" style="color:#1877f2;"></i>
                            <span style="font-size:.72rem;font-weight:600;color:#475569;">Post to Facebook</span>
                            <label style="position:relative;display:inline-block;width:42px;height:24px;cursor:pointer;margin:0;">
                                <input type="checkbox" name="post_to_facebook" id="annPostFb" value="1" style="opacity:0;width:0;height:0;">
                                <span style="position:absolute;inset:0;background:#cdd9b5;border-radius:24px;transition:.3s;" id="fbToggleTrack"></span>
                                <span style="position:absolute;left:2px;bottom:2px;width:20px;height:20px;border-radius:50%;background:#fff;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,0.2);" id="fbToggleThumb"></span>
                            </label>
                        </span>
                    </div>
                    <div class="ann-composer-actions">
                        <button type="button" class="ann-btn ann-btn-ghost" onclick="collapseComposer()">Cancel</button>
                        <button type="submit" class="ann-btn ann-btn-primary" id="annSubmitBtn"><i class="bi bi-send-fill"></i> Publish</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content ann-detail-modal">
            <div class="modal-header ann-detail-bar">
                <button type="button" class="ann-back-btn" onclick="safeHideModal(document.getElementById('viewModal'))">
                    <i class="bi bi-arrow-left"></i> Back to Announcements
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body ann-detail-body" id="viewModalBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="fbSettingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border:2px solid #cdd9b5;border-radius:20px;background:#fef8f3;">
            <div class="modal-header" style="border-bottom:2px solid #cdd9b5;padding:16px 20px;">
                <h5 class="modal-title fw-bold" style="font-size:.95rem;color:#295738;">
                    <i class="bi bi-facebook"></i> Facebook Settings
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="fbSettingsForm">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="fw-semibold mb-1" style="color:#295738;font-size:.85rem;">Facebook Page ID</label>
                        <input type="text" name="facebook_page_id" class="form-control" value="<?= htmlspecialchars($fbPageId) ?>" placeholder="Paste your Page ID here" style="border-radius:14px;border:1px solid #cdd9b5;padding:11px 16px;font-size:.85rem;background:#fff;">
                    </div>
                    <div class="mb-3">
                        <label class="fw-semibold mb-1" style="color:#295738;font-size:.85rem;">Page Access Token</label>
                        <textarea name="facebook_page_access_token" rows="3" class="form-control" placeholder="Paste your Page Access Token here" style="border-radius:14px;border:1px solid #cdd9b5;padding:11px 16px;font-size:.85rem;background:#fff;font-family:monospace;"></textarea>
                        <small style="color:#94a3b8;font-size:.75rem;">Paste the <strong>Page</strong> Access Token from <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a> &rarr; <code>GET /me/accounts</code> (not the User token). The app needs <code>pages_manage_posts</code> + <code>pages_read_engagement</code> and your account must be an admin of the Page.</small>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:2px solid #cdd9b5;padding:14px 20px;">
                    <button type="button" class="btn btn-sm fw-semibold" style="background:transparent;border:1px solid #b5ad55;color:#295738;border-radius:20px;padding:7px 20px;font-size:.8rem;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm fw-semibold" style="background:#295738;color:#fff;border:none;border-radius:20px;padding:7px 24px;font-size:.8rem;"><i class="bi bi-check-lg"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../dashboard/layout/_scripts.php'; ?>
<link rel="stylesheet" href="/public/login/custom-alert.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    var annRoleLabels = <?= json_encode(AnnouncementController::AUDIENCE_ROLES) ?>;
    var DATE_LABELS = { today: 'Today', yesterday: 'Yesterday', week: 'This Week', month: 'This Month', all: 'All Dates', custom: 'Custom Range' };

    var feedData = [];
    var currentCategory = 'public';
    var currentFb = 'any';
    var dateFilter = { mode: 'today', from: '', to: '' };
    var searchTerm = '';
    var editingId = null;
    var editingOriginalImage = '';
    var editRemoveImage = false;
    var submitting = false;
    var notifHandled = false;

    /* ---- Alerts: SweetAlert2, falling back to the existing custom-alert ---- */
    function annAlert(type, title, text) {
        if (window.Swal) {
            Swal.fire({
                icon: type === 'error' ? 'error' : 'success',
                title: title,
                text: text,
                confirmButtonColor: '#295738'
            });
            return;
        }
        if (typeof showAlert === 'function') showAlert(type, title, text);
    }
    function annConfirm(title, text, onConfirm) {
        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: title,
                text: text,
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#6f6d75'
            }).then(function (result) {
                if (result.isConfirmed && typeof onConfirm === 'function') onConfirm();
            });
            return;
        }
        if (typeof showConfirm === 'function') showConfirm(title, text, onConfirm);
    }

    function escapeHtml(t) { if (!t) return ''; var d = document.createElement('div'); d.appendChild(document.createTextNode(t)); return d.innerHTML; }
    function isPrivate(a) { return (a.visibility || 'public').toLowerCase() === 'private'; }
    function isOperationalRow(a) {
        var type = String(a.type || '').trim();
        if (type !== '' && type !== 'System Announcement') return true;
        return !!(String(a.bus_id || '').trim() || String(a.driver_name || '').trim() || String(a.recording_url || '').trim());
    }
    function isTeam(a) { return isPrivate(a) || isOperationalRow(a); }
    function roleNames(csv) {
        return String(csv || '').split(',').map(function (r) { return r.trim(); })
        .filter(function (r) { return r !== ''; })
        .map(function (r) { return annRoleLabels[r] || r; })
        .join(', ');
    }
    function roleLabel(role) { return role ? (annRoleLabels[role] || role) : ''; }

    function parseDt(str) {
        if (!str) return null;
        var p = str.split(' ');
        var dp = p[0].split('-');
        var tp = p[1] ? p[1].split(':') : [0, 0, 0];
        return new Date(+dp[0], +dp[1]-1, +dp[2], +tp[0], +tp[1], +tp[2]);
    }
    function timeSince(date) {
        var s = Math.floor((new Date() - date) / 1000);
        if (s < 60) return 'just now';
        var m = Math.floor(s / 60); if (m < 60) return m + 'm ago';
        var h = Math.floor(m / 60); if (h < 24) return h + 'h ago';
        var d = Math.floor(h / 24); if (d < 7) return d + 'd ago';
        return date.toLocaleDateString('en-US', { month:'short', day:'numeric' });
    }
    function dayLabel(dt) {
        if (!dt) return 'Earlier';
        var now = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        var d = new Date(dt.getFullYear(), dt.getMonth(), dt.getDate());
        var diff = Math.round((today - d) / 86400000);
        if (diff === 0) return 'Today';
        if (diff === 1) return 'Yesterday';
        return dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }

    /* ---- Filters ---- */
    function matchesCategory(a) {
        return currentCategory === 'team' ? isTeam(a) : !isTeam(a);
    }
    function searchHaystack(a) {
        var dt = parseDt(a.created_at);
        var team = isTeam(a);
        var parts = [
            a.title || '',
            a.message || '',
            a.reported_by || '',
            a.author_role || '',
            roleLabel(a.author_role),
            a.type || '',
            a.bus_id || '',
            a.driver_name || '',
            a.priority || '',
            a.status || '',
            team ? 'team feed internal' : 'public update',
            team ? roleNames(a.target_roles) : '',
            team ? (a.target_roles || '') : '',
            a.image_path ? 'photo image attached' : '',
            a.facebook_posted == 1 ? 'facebook posted' : (a.post_to_facebook == 1 ? 'facebook failed' : '')
        ];
        if (dt) {
            parts.push(a.created_at || '');
            parts.push(dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }));
            parts.push(dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }));
            parts.push(dt.toLocaleDateString('en-US', { weekday: 'long' }));
            parts.push(dt.toLocaleDateString('en-US', { month: 'long' }));
            parts.push(dt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }));
        }
        return parts.join(' \u00b7 ').toLowerCase();
    }
    function matchesSearch(a) {
        var term = String(searchTerm || '').toLowerCase().trim();
        if (!term) return true;
        var hay = searchHaystack(a);
        var tokens = term.split(/\s+/).filter(function (t) { return t !== ''; });
        for (var i = 0; i < tokens.length; i++) {
            if (hay.indexOf(tokens[i]) === -1) return false;
        }
        return true;
    }
    function matchesFb(a) {
        if (currentFb === 'posted') return a.facebook_posted == 1;
        if (currentFb === 'failed') return a.post_to_facebook == 1 && a.facebook_posted == 0;
        return true;
    }
    function matchesDate(a) {
        var d = parseDt(a.created_at);
        var now = new Date();
        var startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        if (dateFilter.mode === 'today') return !!d && d >= startOfDay && d <= now;
        if (dateFilter.mode === 'yesterday') {
            var y = new Date(startOfDay.getTime() - 86400000);
            return !!d && d >= y && d < startOfDay;
        }
        if (dateFilter.mode === 'week') {
            var ws = new Date(startOfDay); ws.setDate(ws.getDate() - ws.getDay());
            return !!d && d >= ws;
        }
        if (dateFilter.mode === 'month') {
            var ms = new Date(now.getFullYear(), now.getMonth(), 1);
            return !!d && d >= ms;
        }
        if (dateFilter.mode === 'custom') {
            if (!dateFilter.from && !dateFilter.to) return true;
            if (!d) return false;
            if (dateFilter.from) { var from = new Date(dateFilter.from + 'T00:00:00'); if (d < from) return false; }
            if (dateFilter.to) { var to = new Date(dateFilter.to + 'T23:59:59'); if (d > to) return false; }
            return true;
        }
        return true;
    }

    function setCategory(cat) {
        currentCategory = (cat === 'team') ? 'team' : 'public';
        document.querySelectorAll('#annTabs .ann-tab').forEach(function (t) {
            t.classList.toggle('active', t.dataset.category === currentCategory);
        });
        if (!editingId) setAnnAudience(currentCategory === 'team' ? 'private' : 'public');
        renderFeed();
    }
    function setFbFilter(value) { currentFb = value || 'any'; renderFeed(); }
    function setDateFilter(mode) {
        if (mode === 'custom') return;
        dateFilter = { mode: mode, from: '', to: '' };
        updateDateUi(); closeFab(); renderFeed();
    }
    function applyCustomRange() {
        var from = document.getElementById('annFrom').value || '';
        var to = document.getElementById('annTo').value || '';
        if (!from && !to) { annAlert('error', 'Date range', 'Pick a start and/or end date.'); return; }
        dateFilter = { mode: 'custom', from: from, to: to };
        updateDateUi(); closeFab(); renderFeed();
    }
    function resetDateFilter() {
        dateFilter = { mode: 'today', from: '', to: '' };
        document.getElementById('annFrom').value = '';
        document.getElementById('annTo').value = '';
        updateDateUi(); closeFab(); renderFeed();
    }
    function resetAllFilters() {
        searchTerm = '';
        document.getElementById('annSearch').value = '';
        currentFb = 'any';
        document.getElementById('annFbFilter').value = 'any';
        dateFilter = { mode: 'today', from: '', to: '' };
        updateDateUi(); renderFeed();
    }
    function toggleFab() {
        var panel = document.getElementById('annFabPanel');
        var open = (panel.style.display === 'none' || !panel.style.display);
        panel.style.display = open ? 'block' : 'none';
        document.getElementById('annFabBtn').classList.toggle('open', open);
    }
    function closeFab() {
        document.getElementById('annFabPanel').style.display = 'none';
        document.getElementById('annFabBtn').classList.remove('open');
    }
    function updateDateUi() {
        var label = DATE_LABELS[dateFilter.mode] || 'Today';
        document.getElementById('annDateChipLabel').textContent = label;
        document.getElementById('annFabLabel').textContent = label;
        document.querySelectorAll('#annFabPanel .ann-fab-opt').forEach(function (b) {
            b.classList.toggle('active', b.dataset.date === dateFilter.mode);
        });
        document.getElementById('annFabBtn').classList.toggle('active', dateFilter.mode !== 'today');
    }

    /* ---- Rendering ---- */
    function summaryHtml() {
        var published = feedData.filter(function (a) { return (a.status || 'Active') === 'Active'; }).length;
        var today = feedData.filter(function (a) { var d = parseDt(a.created_at); var now = new Date(); var sod = new Date(now.getFullYear(), now.getMonth(), now.getDate()); return !!d && d >= sod && d <= now; }).length;
        document.getElementById('annSummary').textContent = published + ' published \u00b7 ' + today + ' today';
    }
    function emptyHtml() {
        var categoryItems = feedData.filter(matchesCategory);
        if (categoryItems.length === 0) {
            return '<div class="empty-state"><i class="bi bi-megaphone"></i><h6>No announcements yet</h6><p>' +
                (currentCategory === 'team'
                    ? 'Team updates from drivers, inspectors, dispatchers, finance, and admins will appear here.'
                    : 'Publish your first public announcement using the composer above.') +
                '</p></div>';
        }
        return '<div class="empty-state"><i class="bi bi-funnel"></i><h6>No announcements match your filters</h6><p>Try a wider date range, another search term, or reset the filters.</p>' +
            '<button type="button" class="ann-btn ann-btn-ghost" style="margin-top:14px;" onclick="resetAllFilters()">Reset filters</button></div>';
    }

    function filterSummaryText() {
        var parts = [];
        if (dateFilter.mode === 'custom' && (dateFilter.from || dateFilter.to)) {
            parts.push('Custom: ' + (dateFilter.from || 'any') + ' \u2192 ' + (dateFilter.to || 'any'));
        } else {
            parts.push(DATE_LABELS[dateFilter.mode] || 'Today');
        }
        parts.push(currentCategory === 'team' ? 'Team Feed' : 'Public Updates');
        if (currentFb === 'posted') parts.push('Facebook: Posted');
        else if (currentFb === 'failed') parts.push('Facebook: Failed');
        if (searchTerm) parts.push('Search: "' + searchTerm + '"');
        return parts.join(' \u00b7 ');
    }
    function updateFilterSummary() {
        var el = document.getElementById('annFilterSummary');
        if (!el) return;
        el.innerHTML = '<i class="bi bi-funnel-fill"></i> ' + escapeHtml('Showing: ' + filterSummaryText());
    }

    function renderFeed() {
        summaryHtml();
        updateFilterSummary();

        var filtered = feedData.filter(function (a) {
            return matchesSearch(a) && matchesFb(a) && matchesDate(a);
        });
        var publicCount = filtered.filter(function (a) { return !isTeam(a); }).length;
        var teamCount = filtered.filter(function (a) { return isTeam(a); }).length;
        document.getElementById('tabCountPublic').textContent = publicCount;
        document.getElementById('tabCountTeam').textContent = teamCount;

        var container = document.getElementById('announcementList');
        var items = filtered.filter(matchesCategory);
        document.getElementById('annCount').textContent = items.length + ' item' + (items.length !== 1 ? 's' : '');

        if (items.length === 0) { container.innerHTML = emptyHtml(); return; }

        var html = '<div class="ann-feed">';
        var lastDayKey = null;
        items.forEach(function (a) {
            var dt = parseDt(a.created_at);
            var dayKey = dt ? (dt.getFullYear() + '-' + (dt.getMonth() + 1) + '-' + dt.getDate()) : 'unknown';
            if (dayKey !== lastDayKey) {
                html += '<div class="feed-day-divider"><span>' + escapeHtml(dayLabel(dt)) + '</span></div>';
                lastDayKey = dayKey;
            }

            var fbBadge = '';
            if (a.facebook_posted == 1) fbBadge = '<span class="fb-badge fb-repost-badge"><i class="bi bi-facebook"></i> Posted</span>';
            else if (a.post_to_facebook == 1 && a.facebook_posted == 0) fbBadge = '<span class="fb-badge fb-badge-danger"><i class="bi bi-exclamation-circle-fill"></i> FB Failed</span>';

            var priv = isTeam(a);
            var audienceNames = priv ? roleNames(a.target_roles) : '';
            var categoryBadge = priv
                ? '<span class="fb-badge fb-badge-team"><i class="bi bi-people-fill"></i> Team</span>'
                : '<span class="fb-badge fb-badge-success"><i class="bi bi-globe2"></i> Public Update</span>';
            var audienceHtml = (priv && audienceNames)
                ? '<div class="fb-audience"><i class="bi bi-eye-slash-fill"></i> Visible to: ' + escapeHtml(audienceNames) + '</div>'
                : '';
            var roleChip = a.author_role ? '<span class="fb-role">' + escapeHtml(roleLabel(a.author_role)) + '</span>' : '';

            var dateStr = dt ? dt.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' }) : '-';
            var timeStr = dt ? dt.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' }) : '';
            var timeAgo = dt ? timeSince(dt) : '';

            var retryBtn = (a.post_to_facebook == 1 && a.facebook_posted == 0)
                ? '<button type="button" class="fb-action" onclick="repostFb(' + a.id + ')"><i class="bi bi-facebook"></i> Retry</button>'
                : '';

            html += '<div class="fb-post" data-id="' + a.id + '">' +
            '<div class="fb-body">' +
            '<div class="d-flex align-items-start gap-2 mb-2">' +
            '<div class="fb-avatar">' + escapeHtml((a.reported_by || 'A').charAt(0).toUpperCase()) + '</div>' +
            '<div style="flex:1;min-width:0;">' +
            '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">' +
            '<span style="font-weight:700;color:#295738;font-size:.82rem;">' + escapeHtml(a.reported_by || 'Admin') + '</span>' +
            roleChip +
            '<span class="fb-time">' + timeAgo + '</span>' +
            '</div>' +
            '<div style="font-weight:600;color:#1e293b;font-size:.82rem;margin-top:3px;line-height:1.3;">' + escapeHtml(a.title) + '</div>' +
            '</div>' +
            '</div>' +
            '<div class="d-flex gap-1 mb-2 flex-wrap">' + categoryBadge + fbBadge + '</div>' +
            audienceHtml +
            '<div class="fb-content" data-id="' + a.id + '" onclick="openAnnouncement(this)">' + escapeHtml(a.message) + '</div>' +
            (a.image_path ? '<div style="margin-top:12px;"><img src="' + a.image_path + '" class="fb-image" loading="lazy"></div>' : '') +
            '<div class="fb-actions">' +
            '<small style="color:#94a3b8;font-size:10px;flex:1;padding-top:4px;"><i class="bi bi-clock me-1"></i>' + dateStr + ' \u00b7 ' + timeStr + '</small>' +
            retryBtn +
            '<button type="button" class="fb-action" onclick="startEdit(' + a.id + ')"><i class="bi bi-pencil-square"></i> Edit</button>' +
            '<button type="button" class="fb-action fb-action-danger" onclick="deleteAnn(' + a.id + ')"><i class="bi bi-trash3"></i> Delete</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        });
        html += '</div>';
        container.innerHTML = html;

        items.forEach(function (a) {
            var node = container.querySelector('.fb-post[data-id="' + a.id + '"]');
            if (node) node._ann = a;
        });
    }

    function loadAnnouncements() {
        var container = document.getElementById('announcementList');
        fetch('/admin/api/announcement/list.php?_=' + Date.now())
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) {
                container.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-circle" style="color:#e74c3c;"></i><h6>Failed to load</h6><p>Please refresh the page.</p></div>';
                return;
            }
            feedData = res.data || [];
            renderFeed();
            openAnnouncementFromNotif();
        })
        .catch(function () {
            container.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-circle" style="color:#e74c3c;"></i><h6>Failed to load</h6><p>Please refresh the page.</p></div>';
        });
    }

    /* ---- Composer ---- */
    function setAnnAudience(visibility) {
        if (visibility !== 'private') visibility = 'public';
        document.querySelectorAll('#annAudienceToggle .ann-audience-option').forEach(function (o) {
            var active = o.dataset.visibility === visibility;
            o.classList.toggle('active', active);
            var input = o.querySelector('input');
            if (input) input.checked = active;
        });
        document.getElementById('annAudienceRoles').style.display = visibility === 'private' ? 'flex' : 'none';
    }
    function setRoleCheckboxes(csv) {
        var keys = null;
        if (csv) {
            keys = String(csv).split(',').map(function (r) { return r.trim(); }).filter(function (r) { return r !== ''; });
        }
        document.querySelectorAll('#annAudienceRoles input[name="target_roles[]"]').forEach(function (cb) {
            cb.checked = keys ? keys.indexOf(cb.value) !== -1 : true;
        });
    }
    function expandComposer() {
        var modalEl = document.getElementById('composerModal');
        if (modalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }
    function resetComposer() {
        editingId = null;
        editingOriginalImage = '';
        editRemoveImage = false;
        document.getElementById('announcementForm').reset();
        document.getElementById('annImage').value = '';
        document.getElementById('annImagePreview').style.display = 'none';
        document.getElementById('annImagePreviewImg').src = '';
        document.getElementById('annRemoveImageWrap').style.display = 'none';
        document.getElementById('annComposerTitle').textContent = 'Create announcement';
        document.getElementById('annSubmitBtn').innerHTML = '<i class="bi bi-send-fill"></i> Publish';
        document.getElementById('annEditId').value = '';
        setRoleCheckboxes(null);
        setAnnAudience(currentCategory === 'team' ? 'private' : 'public');
        document.querySelector('#annAudienceToggle .ann-audience-option[data-visibility="public"]').style.display = '';
        document.getElementById('annAudienceNote').style.display = 'none';
        var track = document.getElementById('fbToggleTrack');
        var thumb = document.getElementById('fbToggleThumb');
        if (track) track.style.background = '#cdd9b5';
        if (thumb) thumb.style.transform = 'translateX(0)';
    }
    function collapseComposer() {
        resetComposer();
        var modalEl = document.getElementById('composerModal');
        var modal = (modalEl && window.bootstrap) ? bootstrap.Modal.getInstance(modalEl) : null;
        if (modal) {
            modal.hide();
        } else {
            forceUnlockScroll();
        }
    }
    function startEdit(id) {
        var a = null;
        for (var i = 0; i < feedData.length; i++) { if (feedData[i].id == id) { a = feedData[i]; break; } }
        if (!a) return;

        editingId = a.id;
        editingOriginalImage = a.image_path || '';
        editRemoveImage = false;

        expandComposer();
        document.getElementById('annComposerTitle').textContent = 'Edit announcement';
        document.getElementById('annSubmitBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Changes';
        document.getElementById('annEditId').value = a.id;
        document.getElementById('annTitle').value = a.title || '';
        document.getElementById('annMessage').value = a.message || '';

        var priv = isTeam(a);
        var operational = isOperationalRow(a);
        document.querySelector('#annAudienceToggle .ann-audience-option[data-visibility="public"]').style.display = operational ? 'none' : '';
        document.getElementById('annAudienceNote').style.display = operational ? 'inline' : 'none';
        setAnnAudience((operational || priv) ? 'private' : 'public');
        setRoleCheckboxes((operational || priv) ? a.target_roles : null);

        var preview = document.getElementById('annImagePreview');
        var img = document.getElementById('annImagePreviewImg');
        if (a.image_path) {
            img.src = a.image_path;
            preview.style.display = 'block';
            document.getElementById('annRemoveImageWrap').style.display = 'inline-flex';
        } else {
            img.src = '';
            preview.style.display = 'none';
            document.getElementById('annRemoveImageWrap').style.display = 'none';
        }
        document.getElementById('annImage').value = '';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function previewAnnImage(input) {
        var preview = document.getElementById('annImagePreview');
        var img = document.getElementById('annImagePreviewImg');
        if (input.files && input.files[0]) {
            editRemoveImage = false;
            document.getElementById('annRemoveImageWrap').style.display = 'none';
            var reader = new FileReader();
            reader.onload = function (e) { img.src = e.target.result; preview.style.display = 'block'; };
            reader.readAsDataURL(input.files[0]);
        }
    }
    function clearAnnImage() {
        document.getElementById('annImage').value = '';
        var preview = document.getElementById('annImagePreview');
        var img = document.getElementById('annImagePreviewImg');
        if (editingId && editingOriginalImage) {
            img.src = editingOriginalImage;
            preview.style.display = 'block';
            editRemoveImage = false;
            document.getElementById('annRemoveImageWrap').style.display = 'inline-flex';
        } else {
            img.src = '';
            preview.style.display = 'none';
            editRemoveImage = false;
        }
    }
    function removeAnnImage() {
        editRemoveImage = true;
        document.getElementById('annImage').value = '';
        document.getElementById('annImagePreview').style.display = 'none';
        document.getElementById('annImagePreviewImg').src = '';
        document.getElementById('annRemoveImageWrap').style.display = 'none';
    }

    /* ---- Detail overlay ---- */
    function openAnnouncement(el) {
        var card = el.closest('.fb-post');
        var a = card ? card._ann : null;
        if (!a) return;

        var title = a.title || '';
        var message = a.message || '';
        var type = a.type || 'Announcement';
        var author = a.reported_by || 'Admin';
        var status = a.status || 'Active';

        var dt = parseDt(a.created_at);
        var dateLong = dt ? dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '-';
        var dateFull = dt ? dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) + ' at ' + dt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '-';

        var authorHtml;
        var imgEl = card ? card.querySelector('img[style*="border-radius:50%"]') : null;
        var initEl = card ? card.querySelector('.fb-avatar') : null;
        if (imgEl) {
            authorHtml = '<img src="' + imgEl.src + '" class="ann-avatar" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid #cdd9b5;">';
        } else {
            var letter = (initEl && initEl.innerHTML) ? initEl.innerHTML : (author || 'A').charAt(0).toUpperCase();
            authorHtml = '<div class="ann-avatar">' + escapeHtml(letter) + '</div>';
        }

        var heroHtml;
        if (a.image_path) {
            heroHtml = '<img src="' + a.image_path + '" class="ann-hero" loading="lazy" alt="' + escapeHtml(title) + '">';
        } else {
            heroHtml = '<div class="ann-hero-placeholder"><i class="bi bi-card-image"></i><span>No image attached</span></div>';
        }

        var highlightHtml = '';
        var highlightMatch = extractReminder(message);
        if (highlightMatch) {
            highlightHtml =
            '<div class="ann-highlight">' +
            '<div class="ann-highlight-title"><i class="bi bi-check-circle-fill"></i> ' + escapeHtml(highlightMatch.title) + '</div>' +
            '<div class="ann-highlight-text">' + escapeHtml(highlightMatch.text) + '</div>' +
            '</div>';
        }

        var statusIcon = (status === 'Active') ? '<i class="bi bi-check-circle-fill" style="color:#2e7d32;"></i> Published' : '<i class="bi bi-pause-circle-fill" style="color:#c62828;"></i> ' + escapeHtml(status);

        var priv = isTeam(a);
        var audienceNames = priv ? roleNames(a.target_roles) : '';
        var authorRole = roleLabel(a.author_role);

        document.getElementById('viewModalBody').innerHTML =
        '<div class="ann-detail-wrap">' +

        '<span class="ann-cat-badge"><i class="bi ' + (priv ? 'bi-people-fill' : 'bi-globe2') + '"></i> ' + (priv ? 'Team Feed' : 'Public Update') + '</span>' +

        '<h1 class="ann-detail-title">' + escapeHtml(title) + '</h1>' +

        '<div class="ann-meta-row">' +
        '<span>Posted by ' + escapeHtml(author) + (authorRole ? ' \u00b7 ' + escapeHtml(authorRole) : '') + '</span>' +
        '<span class="ann-meta-dot">\u2022</span>' +
        '<span>' + dateLong + '</span>' +
        '</div>' +

        '<div class="ann-author">' +
        authorHtml +
        '<div>' +
        '<div class="ann-author-name">' + escapeHtml(author) + '</div>' +
        '<div class="ann-author-posted">Posted ' + dateFull + '</div>' +
        '</div>' +
        '</div>' +

        heroHtml +

        '<div class="ann-content">' + escapeHtml(message) + '</div>' +

        highlightHtml +

        '<div class="ann-detail-footer">' +
        '<span class="ann-foot-chip"><i class="bi bi-calendar3"></i> Published ' + dateFull + '</span>' +
        '<span class="ann-foot-chip">' + statusIcon + '</span>' +
        (priv
            ? '<span class="ann-foot-chip"><i class="bi bi-people-fill"></i> Team' + (audienceNames ? ' \u00b7 ' + escapeHtml(audienceNames) : '') + '</span>'
            : '<span class="ann-foot-chip"><i class="bi bi-globe2"></i> Public Update</span>') +
        (a.priority ? '<span class="ann-foot-chip"><i class="bi bi-flag"></i> ' + escapeHtml(a.priority) + '</span>' : '') +
        (a.facebook_posted == 1 ? '<span class="ann-foot-chip"><i class="bi bi-facebook"></i> Posted on Facebook</span>' : '') +
        '</div>' +

        '</div>';

        var modal = new bootstrap.Modal(document.getElementById('viewModal'));
        modal.show();
    }

    function extractReminder(text) {
        if (!text) return null;
        var lines = String(text).split(/\r?\n/);
        for (var i = 0; i < lines.length; i++) {
            var m = lines[i].match(/^\s*(IMPORTANT REMINDER|IMPORTANT|REMINDER|NOTE|ALERT)\s*[:.\-]?\s*(.*)$/i);
            if (m && m[2]) return { title: m[1], text: m[2] };
            if (m && !m[2] && lines[i + 1] && lines[i + 1].trim()) return { title: m[1], text: lines[i + 1].trim() };
        }
        return null;
    }

    function openAnnouncementFromNotif() {
        if (notifHandled) return;
        var params = new URLSearchParams(window.location.search);
        var id = params.get('open_announcement');
        if (!id || !/^\d+$/.test(id)) return;

        var el = document.querySelector('.fb-content[data-id="' + id + '"]');
        if (!el) {
            var target = null;
            for (var i = 0; i < feedData.length; i++) { if (feedData[i].id == id) { target = feedData[i]; break; } }
            if (target) {
                var changed = false;
                var cat = isTeam(target) ? 'team' : 'public';
                if (cat !== currentCategory) {
                    currentCategory = cat;
                    document.querySelectorAll('#annTabs .ann-tab').forEach(function (t) {
                        t.classList.toggle('active', t.dataset.category === cat);
                    });
                    changed = true;
                }
                if (dateFilter.mode !== 'all') {
                    dateFilter = { mode: 'all', from: '', to: '' };
                    updateDateUi();
                    changed = true;
                }
                if (searchTerm || currentFb !== 'any') {
                    searchTerm = '';
                    document.getElementById('annSearch').value = '';
                    currentFb = 'any';
                    document.getElementById('annFbFilter').value = 'any';
                    changed = true;
                }
                if (changed) renderFeed();
                el = document.querySelector('.fb-content[data-id="' + id + '"]');
            }
        }
        if (el) {
            notifHandled = true;
            openAnnouncement(el);
        }
    }

    /* ---- Modal helpers ---- */
    function safeHideModal(el) {
        var m = bootstrap.Modal.getInstance(el);
        if (m) m.hide();
        forceUnlockScroll();
    }
    function forceUnlockScroll() {
        document.querySelectorAll('.modal-backdrop').forEach(function (b) { b.remove(); });
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    /* ---- Post actions ---- */
    function deleteAnn(id) {
        annConfirm('Delete announcement?', 'This cannot be undone.', function () {
            var form = new FormData(); form.append('id', id);
            fetch('/admin/api/announcement/delete.php', { method:'POST', body:form })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    if (editingId == id) collapseComposer();
                    annAlert('success', 'Deleted!', 'Announcement has been deleted.');
                    loadAnnouncements();
                } else {
                    annAlert('error', 'Error', res.message);
                }
            });
        });
    }

    function repostFb(id) {
        var form = new FormData(); form.append('id', id);
        fetch('/admin/api/announcement/republish.php', { method:'POST', body:form })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) annAlert('success', 'Facebook Post Created!', 'Post published to Facebook successfully.');
            else {
                var errorMsg = res.facebook_error || res.message || 'Failed.';
                if (res.facebook_error_type === 'token_expired') errorMsg = 'Your Facebook access token has expired. Go to Announcement Settings to update it.';
                else if (res.facebook_error_type === 'permission') errorMsg = 'The saved token is not allowed to post to this Page. Use the Page Access Token from Graph API Explorer -> GET /me/accounts (not the User token), make sure the app has pages_manage_posts and pages_read_engagement, and that your account is an admin of the Page.';
                annAlert('error', 'Facebook Error', errorMsg);
            }
            loadAnnouncements();
        })
        .catch(function () { annAlert('error', 'Error', 'Network error.'); loadAnnouncements(); });
    }

    /* ---- Submit (create + edit) ---- */
    document.getElementById('announcementForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (submitting) return;

        var visibilityInput = this.querySelector('input[name="visibility"]:checked');
        var visibility = visibilityInput ? visibilityInput.value : 'public';
        if (visibility === 'private' && this.querySelectorAll('input[name="target_roles[]"]:checked').length === 0) {
            annAlert('error', 'Audience required', 'Select at least one role for a Team Feed post.');
            return;
        }

        submitting = true;
        var btn = document.getElementById('annSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

        var wasEditing = !!editingId;
        var formData = new FormData(this);
        if (wasEditing) {
            formData.set('id', editingId);
            if (editRemoveImage) formData.set('remove_image', '1');
        }
        var url = wasEditing ? '/admin/api/announcement/update.php' : '/admin/api/announcement/save.php';

        fetch(url, { method:'POST', body:formData })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                var msg = wasEditing ? 'Announcement updated successfully.' : 'Announcement published successfully.';
                if (res.facebook_post_id) msg += '\nFacebook post created successfully.';
                else if (res.facebook_error) {
                    var displayError = res.facebook_error;
                    if (res.facebook_error_type === 'token_expired') displayError = 'Your Facebook access token has expired. Go to Announcement Settings to update it.';
                    else if (res.facebook_error_type === 'permission') displayError = 'The saved token is not allowed to post to this Page. Use the Page Access Token from Graph API Explorer -> GET /me/accounts (not the User token), make sure the app has pages_manage_posts and pages_read_engagement, and that your account is an admin of the Page.';
                    msg += '\nAnnouncement saved. Facebook posting failed: ' + displayError;
                }
                annAlert('success', wasEditing ? 'Updated!' : 'Published!', msg);
                collapseComposer();
                loadAnnouncements();
            } else {
                annAlert('error', 'Error', res.message || 'Failed to save.');
            }
        })
        .catch(function () { annAlert('error', 'Error', 'Network error.'); })
        .finally(function () {
            submitting = false;
            btn.disabled = false;
            btn.innerHTML = editingId ? '<i class="bi bi-check-lg"></i> Save Changes' : '<i class="bi bi-send-fill"></i> Publish';
        });
    });

    /* ---- Facebook settings ---- */
    document.getElementById('annPostFb').addEventListener('change', function () {
        var track = document.getElementById('fbToggleTrack');
        var thumb = document.getElementById('fbToggleThumb');
        if (this.checked) {
            track.style.background = '#1877f2';
            thumb.style.transform = 'translateX(18px)';
        } else {
            track.style.background = '#cdd9b5';
            thumb.style.transform = 'translateX(0)';
        }
    });

    document.getElementById('fbSettingsForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        fetch('/admin/api/announcement/save_settings.php', { method:'POST', body:formData })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                annAlert('success', 'Settings saved!', 'Facebook settings have been updated.');
                safeHideModal(document.getElementById('fbSettingsModal'));
            } else {
                annAlert('error', 'Error', res.message);
            }
        })
        .catch(function () { annAlert('error', 'Error', 'Network error.'); });
    });

    document.querySelectorAll('#annAudienceToggle .ann-audience-option input').forEach(function (input) {
        input.addEventListener('change', function () { setAnnAudience(this.value); });
    });

    document.addEventListener('hidden.bs.modal', function () { forceUnlockScroll(); });

    document.addEventListener('click', function (e) {
        var wrap = document.querySelector('.ann-fab-wrap');
        if (wrap && !wrap.contains(e.target)) closeFab();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeFab();
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('annSearch').addEventListener('input', function () {
            searchTerm = (this.value || '').toLowerCase().trim();
            renderFeed();
        });
        setAnnAudience('public');
        updateDateUi();
        loadAnnouncements();
    });
</script>

<script src="/public/login/custom-alert.js?v=<?= time() ?>"></script>

<div id="customAlertOverlay" class="custom-alert-overlay">
    <div class="custom-alert-card">
        <div class="custom-alert-header">
            <div class="custom-alert-icon"></div>
            <div class="custom-alert-title"></div>
        </div>
        <div class="custom-alert-body">
            <div class="custom-alert-message"></div>
            <div class="custom-alert-actions">
                <button class="custom-alert-btn"></button>
                <button class="custom-alert-btn-cancel"></button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../dashboard/layout/_footer.php'; ?>
