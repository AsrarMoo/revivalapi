<?php

return [
    'supports_credentials' => true,

    'allowed_origins' => ['*'],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
    ],

    'allowed_methods' => ['*'],  // السماح بجميع أنواع الطلبات (GET, POST, PUT, DELETE, ...)

    'exposed_headers' => [],

    'max_age' => 0,

    'paths' => [
        'api/*',  // السماح لجميع مسارات الـ API
        'login',  // السماح لمسار login
        'register',  // السماح لمسار register
        'profile',  // السماح لمسار profile
    ],
];
