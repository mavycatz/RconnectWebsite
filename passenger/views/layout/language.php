<?php
$passengerLang = function_exists('passenger_current_language') ? passenger_current_language() : 'en';
$passengerLanguages = function_exists('passenger_available_languages') ? passenger_available_languages() : ['en' => 'English'];
$passengerLangKeys = ['en' => 'login.language_english', 'tl' => 'login.language_tagalog', 'ilo' => 'login.language_ilocano'];
$passengerTranslations = function_exists('passenger_translations') ? passenger_translations($passengerLang) : [];
$passengerLangAlign = isset($passengerLangAlign) && in_array($passengerLangAlign, ['flex-start', 'center', 'flex-end'], true) ? $passengerLangAlign : 'flex-end';
?>
<div class="passenger-lang-wrap" style="display:flex;justify-content:<?= $passengerLangAlign ?>;align-items:center;gap:6px;">
    <i class="bi bi-translate" style="color:#47613b;font-size:14px;"></i>
    <select class="passenger-lang-select" aria-label="<?= htmlspecialchars(passenger_t('login.language', 'Language'), ENT_QUOTES, 'UTF-8') ?>" onchange="passengerChangeLanguage(this.value)" style="border:1px solid #c6d48c;background:#fff;color:#24513c;border-radius:10px;padding:4px 8px;font-size:12px;font-family:'Poppins';">
        <?php foreach ($passengerLanguages as $code => $label): ?>
        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"<?= $code === $passengerLang ? ' selected' : '' ?>><?= htmlspecialchars($passengerTranslations[$passengerLangKeys[$code] ?? 'login.language_english'] ?? $label, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
    </select>
</div>
<script>
if (typeof window.passengerChangeLanguage !== 'function') {
    window.passengerChangeLanguage = function(lang){
        try { localStorage.setItem('passenger_lang', lang); } catch(e) {}
        const url = new URL(window.location.href);
        url.searchParams.set('lang', lang);
        window.location.href = url.toString();
    };
}
(function(){
    try {
        const stored = localStorage.getItem('passenger_lang');
        const params = new URLSearchParams(window.location.search);
        const current = <?= json_encode($passengerLang) ?>;
        if(stored && stored !== current && !params.get('lang')){
            params.set('lang', stored);
            window.location.replace(window.location.pathname + '?' + params.toString());
        }
    } catch(e) {}
})();
</script>
