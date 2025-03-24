<?php
return [
   'paths' => ['*'],
    'allowed_methods' => ['*'],  // السماح بكل طرق HTTP
    'allowed_origins' => ['*'],  // السماح بكل المصادر (يمكنك تحديد نطاق معين بدلاً من `*`)
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],  // السماح بكل الهيدرز
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // السماح باستخدام الـ Cookies
];

