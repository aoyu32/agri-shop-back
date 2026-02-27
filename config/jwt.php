<?php

return [
    // JWT 密钥（生产环境请使用更复杂的密钥，并通过环境变量配置）
    'key' => env('jwt.key', 'agri_shop_secret_key_2024_change_in_production'),

    // 加密算法
    'alg' => 'HS256',

    // Token 过期时间（秒）
    'expire' => 86400, // 24小时

    // 记住我的过期时间（秒）
    'remember_expire' => 604800, // 7天

    // 签发者
    'iss' => 'agri-shop',

    // 接收者
    'aud' => 'agri-shop-client',
];
