<?php
// 全局中间件定义文件
return [
    // CORS 跨域中间件（必须放在最前面）
    \app\middleware\Cors::class,
    // 全局请求缓存
    // \think\middleware\CheckRequestCache::class,
    // 多语言加载
    // \think\middleware\LoadLangPack::class,
    // Session初始化
    // \think\middleware\SessionInit::class
];
