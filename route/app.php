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

// 分类相关路由
Route::group('api/category', function () {
    // 获取分类列表
    Route::get('list', 'CategoryController/list');

    // 获取分类树
    Route::get('tree', 'CategoryController/tree');

    // 获取分类详情
    Route::get('detail', 'CategoryController/detail');
});

// 商品相关路由
Route::group('api/product', function () {
    // 获取热销商品
    Route::get('hot', 'ProductController/hotList');

    // 获取促销商品
    Route::get('promotion', 'ProductController/promotionList');

    // 获取所有商品（分页）
    Route::get('list', 'ProductController/list');

    // 高级筛选商品
    Route::get('filter', 'ProductController/filter');

    // 获取产地列表
    Route::get('origins', 'ProductController/getOrigins');

    // 获取推荐商品
    Route::get('recommend', 'ProductController/recommend');

    // 根据分类获取商品
    Route::get('category', 'ProductController/listByCategory');

    // 获取商品详情
    Route::get('detail', 'ProductController/detail');

    // 搜索商品
    Route::get('search', 'ProductController/search');
});

// 店铺相关路由
Route::group('api/shop', function () {
    // 获取推荐店铺
    Route::get('recommended', 'ShopController/recommendedList');

    // 获取店铺详情
    Route::get('detail', 'ShopController/detail');
});
