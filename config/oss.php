<?php

return [
    // 阿里云OSS配置
    'endpoint' => 'oss-cn-beijing.aliyuncs.com',
    'access_key_id' => 'LTAI5tMkfPnuazLWJdAbh9Ju',
    'access_key_secret' => 'abN46L9N2YkTxSEYmOMVnSuBGqXS7h',
    'bucket' => 'agri-shop-back',

    // 自定义域名（如果有的话，没有则使用默认域名）
    'domain' => '',

    // 上传目录配置
    'upload_path' => [
        'product' => 'uploads/products/',      // 商品图片
        'shop' => 'uploads/shops/',            // 店铺图片
        'user' => 'uploads/users/',            // 用户头像
        'category' => 'uploads/categories/',   // 分类图片
        'other' => 'uploads/others/',          // 其他图片
    ],

    // 允许上传的文件类型
    'allowed_ext' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],

    // 文件大小限制（单位：字节，默认5MB）
    'max_size' => 5 * 1024 * 1024,
];
