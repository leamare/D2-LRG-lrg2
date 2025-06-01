<?php 

return [
    "en" => [
        "file" => __DIR__ . "/en.json",
        "name" => "English",
        'vpk'  => "english",
    ],
    "def" => [
        "alias" => "en",
    ],
    "ru" => [
        "file" => __DIR__ . "/ru.json",
        "name" => "Русский",
        'vpk'  => "russian",
    ],
    "uk" => [
        "fallback" => "ru",
        "file" => __DIR__ . "/uk.json",
        "name" => "Українська",
        'vpk'  => "ukrainian",
    ],
    "ua" => [
        "alias" => "uk",
    ],
    "pt" => [
        "beta" => true,
        "fallback" => "pt-br",
        "file" => __DIR__ . "/pt.json",
        "name" => "Português",
        'vpk'  => "portuguese",
    ],
    "pt-br" => [
        "file" => __DIR__ . "/pt-br.json",
        "name" => "Português Brasileiro",
        'vpk'  => "brazilian",
    ],
    "zh" => [
        "file" => __DIR__ . "/zh.json",
        "name" => "普通话",
        'vpk'  => "schinese",
    ],
    "emoji" => [
        "file" => __DIR__ . "/emoji.json",
        "name" => "Emoji 😀",
    ],
];
