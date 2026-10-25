<?php if (!defined('APP_LOADED')) exit('No direct script access allowed'); ?>
<?php require_once __DIR__ . '/_content_data.php'; ?>

<?php $doc = $docs[$page] ?? $docs['finance-guide']; ?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/../layout/navbarfinance.php'; ?>

<div class="main-content" style="font-family:'Poppins',sans-serif;background:#eceed9;padding:20px;">
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">
        <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>

        <a href="/finance/documentation/<?= $_GET['token'] ?>" class="btn btn-sm px-3 mb-3" style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:10px;font-size:12px;font-weight:500;color:#2e4836;text-decoration:none;">
            ← Back to Documentation
        </a>

        <div class="card shadow-sm border-0" style="border-radius:16px;">
            <div class="card-body p-4">
                <h4 style="font-weight:700;color:#295738;margin:0 0 4px;"><?= htmlspecialchars($doc['title']) ?></h4>
                <hr style="border-color:#e3eabb;margin:16px 0;">

                <?php foreach ($doc['sections'] as $section): ?>
                <div class="mb-4">
                    <h5 style="font-weight:600;font-size:0.95rem;color:#1a6b3c;margin:0 0 8px;padding-bottom:4px;border-bottom:2px solid #e3eabb;">
                        <?= htmlspecialchars($section['heading']) ?>
                    </h5>
                    <p style="font-size:0.85rem;color:#444;line-height:1.8;margin:0;white-space:pre-line;">
                        <?= htmlspecialchars($section['content']) ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<script>
var tk = new URLSearchParams(window.location.search).get("token");
if (tk) {
    document.querySelectorAll('a[href^="/finance/"]').forEach(function (a) {
        var path = a.getAttribute("href");
        if (path.indexOf("/finance/") === 0 && path.indexOf(tk) === -1) {
            var page = path.replace("/finance/", "").replace(".php", "").split("?")[0];
            var qs = path.indexOf("?") !== -1 ? path.substring(path.indexOf("?")) : "";
            a.href = "/finance/" + page + "/" + tk + qs;
        }
    });
}
</script>
