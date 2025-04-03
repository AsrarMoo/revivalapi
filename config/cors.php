<?php
return [
    'paths' => ['*'], // تأكد من أن مسارات API مضمنة
    'allowed_methods' => ['*'], // أو حدد الأساليب المسموحة مثل ['GET', 'POST', 'PUT', 'DELETE']
    'allowed_origins' => ['http://127.0.0.1:8000'], // تأكد من تضمين رابط المتصفح
    'allowed_headers' => ['*'], // السماح لجميع الهيدرات
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
