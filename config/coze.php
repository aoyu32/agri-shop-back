<?php

// Coze AI 配置文件
return [
    // API密钥
    'api_key' => env('coze.api_key', ''),

    // Bot ID
    'bot_id' => env('coze.bot_id', ''),

    // 行情预测 Bot ID
    'market_bot_id' => env('coze.market_bot_id', ''),

    // API地址
    'api_url' => env('coze.api_url', 'https://api.coze.cn/v3/chat'),

    // Stream Run API地址
    'stream_run_url' => env('coze.stream_run_url', ''),

    // Project ID
    'project_id' => env('coze.project_id', ''),

    // 请求超时时间（秒）
    'timeout' => env('coze.timeout', 60),

    // 是否启用流式响应
    'stream' => env('coze.stream', true),

    // 是否自动保存历史
    'auto_save_history' => env('coze.auto_save_history', true),
];
