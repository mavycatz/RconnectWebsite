<?php

function passenger_available_languages()
{
    return [
        'en' => 'English',
        'tl' => 'Tagalog',
        'ilo' => 'Ilocano',
    ];
}

function passenger_current_language()
{
    $allowed = array_keys(passenger_available_languages());

    if (isset($_GET['lang']) && is_string($_GET['lang']) && in_array($_GET['lang'], $allowed, true)) {
        if (isset($_SESSION) && is_array($_SESSION)) {
            $_SESSION['passenger_lang'] = $_GET['lang'];
        }
        return $_GET['lang'];
    }

    $session = isset($_SESSION) && is_array($_SESSION) ? $_SESSION : [];
    $lang = isset($session['passenger_lang']) ? (string) $session['passenger_lang'] : 'en';

    return in_array($lang, $allowed, true) ? $lang : 'en';
}

function passenger_translations($lang = null)
{
    static $cache = [];

    $allowed = array_keys(passenger_available_languages());
    $lang = $lang !== null ? (string) $lang : 'en';
    if (!in_array($lang, $allowed, true)) {
        $lang = 'en';
    }

    if (isset($cache[$lang])) {
        return $cache[$lang];
    }

    $dir = __DIR__ . '/../translations';
    $data = passenger_read_translation_file($dir . '/' . $lang . '.json');

    if ($lang !== 'en') {
        $fallback = passenger_read_translation_file($dir . '/en.json');
        if (!empty($fallback)) {
            $data = array_merge($fallback, $data);
        }
    }

    $cache[$lang] = $data;
    return $data;
}

function passenger_read_translation_file($file)
{
    if (!is_file($file)) {
        return [];
    }

    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function passenger_t($key, $fallback = null, array $replace = [])
{
    global $passenger_translation_table;

    if (!isset($passenger_translation_table) || !is_array($passenger_translation_table)) {
        $passenger_translation_table = passenger_translations(passenger_current_language());
    }

    $text = $passenger_translation_table[$key] ?? ($fallback !== null ? $fallback : $key);
    if (!is_string($text)) {
        $text = (string) $text;
    }

    foreach ($replace as $name => $value) {
        $text = str_replace(':' . $name, (string) $value, $text);
    }

    return $text;
}
