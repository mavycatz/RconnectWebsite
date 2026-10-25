<?php

if (!isset($_SESSION["passenger_id"])) {
    echo "<div style='color:#a93226;padding:16px;text-align:center;font-family:Poppins,sans-serif;'>Session expired</div>";
    exit();
}

$currentLang = passenger_current_language();
$languages = passenger_available_languages();
$languageKeys = ['en' => 'login.language_english', 'tl' => 'login.language_tagalog', 'ilo' => 'login.language_ilocano'];
$translations = passenger_translations($currentLang);
?>
<style>
#languageModal.lm-overlay{position:fixed;inset:0;background:rgba(46,72,54,.35);display:flex;align-items:center;justify-content:center;z-index:9999999;padding:10px;}
#languageModal .lm-box{position:relative;width:100%;max-width:360px;background:#fef8f3;border:2px solid #c7c28a;border-radius:16px;box-shadow:0 6px 18px rgba(0,0,0,.08);padding:24px;font-family:'Poppins',sans-serif;}
#languageModal .lm-close{position:absolute;top:10px;right:15px;font-size:20px;cursor:pointer;color:#2e4836;line-height:1;}
#languageModal .lm-title{font-size:17px;font-weight:700;color:#2e4836;text-align:center;}
#languageModal .lm-sub{font-size:12px;color:#6b7d6b;text-align:center;margin:6px 0 16px;}
#languageModal .lm-options{display:flex;flex-direction:column;gap:8px;}
#languageModal .lm-option{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;border:1px solid #e5e0a4;background:#fff;color:#24513c;border-radius:12px;padding:12px 14px;font-family:'Poppins';font-size:13px;font-weight:600;line-height:1.35;cursor:pointer;text-align:left;}
#languageModal .lm-option:hover{background:#faf9e5;}
#languageModal .lm-option.lm-active{border-color:#47613b;background:#e8f0e0;color:#1b5e20;}
#languageModal .lm-option span{flex:1 1 auto;min-width:0;overflow-wrap:anywhere;word-break:break-word;}
#languageModal .lm-option i{font-size:15px;color:#2e7d32;flex:0 0 auto;}
</style>

<div class="lm-overlay" id="languageModal" onclick="closeLanguageModal(event)">
    <div class="lm-box" onclick="event.stopPropagation()">
        <div class="lm-close" onclick="closeLanguageModal()">&times;</div>
        <div class="lm-title"><?= htmlspecialchars(passenger_t("login.language", "Language"), ENT_QUOTES, "UTF-8") ?></div>
        <div class="lm-sub"><?= htmlspecialchars(passenger_t("language.subtitle", "Choose the language used across the app."), ENT_QUOTES, "UTF-8") ?></div>
        <div class="lm-options">
            <?php foreach ($languages as $code => $label): ?>
            <button type="button" class="lm-option<?= $code === $currentLang ? " lm-active" : "" ?>" onclick="passengerChangeLanguage('<?= htmlspecialchars($code, ENT_QUOTES, "UTF-8") ?>')">
                <span><?= htmlspecialchars($translations[$languageKeys[$code] ?? "login.language_english"] ?? $label, ENT_QUOTES, "UTF-8") ?></span>
                <?php if ($code === $currentLang): ?><i class="bi bi-check-circle-fill"></i><?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>
