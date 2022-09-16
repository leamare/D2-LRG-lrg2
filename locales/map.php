<?php 

return [
    "en" => [
        "file" => __DIR__ . "/en.json",
        "name" => "English",
    ],
    "def" => [
        "alias" => "en",
    ],
    "ru" => [
        "file" => __DIR__ . "/ru.json",
        "name" => "Русский",
    ],
    "uk" => [
        "beta" => true,
        "fallback" => "ru",
        "file" => __DIR__ . "/uk.json",
        "name" => "Українська",
    ],
    "ua" => [
        "alias" => "uk",
    ],
    "pt" => [
        "beta" => true,
        "fallback" => "pt-br",
        "file" => __DIR__ . "/pt.json",
        "name" => "Português",
    ],
    "pt-br" => [
        "file" => __DIR__ . "/pt-br.json",
        "name" => "Português Brasileiro",
    ],
    "zh" => [
        "file" => __DIR__ . "/zh.json",
        "name" => "普通话",
    ]
];
