<?php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'], // المسارات التي تطبق عليها CORS
    'allowed_methods' => ['*'], // جميع الـ HTTP Methods مسموحة
    'allowed_origins' => ['http://127.0.0.1:8000'], // أضف أصولك هنا
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'], // جميع الـ Headers مسموحة
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false, // غيّرها لـ `true` إذا كنت تستخدم الكوكيز
];
