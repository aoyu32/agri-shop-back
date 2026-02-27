<?php

use think\facade\Route;

// API 路由组
Route::group('api', function () {
    // 认证相关路由
    Route::group('auth', function () {
        // 发送验证码
        Route::post('send-code', 'AuthController/sendCode');

        // 用户注册
        Route::post('register', 'AuthController/register');

        // 用户登录
        Route::post('login', 'AuthController/login');

        // 获取用户信息（需要token）
        Route::get('user-info', 'AuthController/getUserInfo')->middleware(\app\middleware\Auth::class);

        // 重置密码
        Route::post('reset-password', 'AuthController/resetPassword');

        // 退出登录（需要token）
        Route::post('logout', 'AuthController/logout')->middleware(\app\middleware\Auth::class);
    });
});
