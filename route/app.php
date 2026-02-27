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

// 购物车相关路由（需要登录）
Route::group('api/cart', function () {
    // 获取购物车列表
    Route::get('list', 'CartController/list');

    // 添加到购物车
    Route::post('add', 'CartController/add');

    // 更新商品数量
    Route::post('update-quantity', 'CartController/updateQuantity');

    // 切换选中状态
    Route::post('toggle-check', 'CartController/toggleCheck');

    // 全选/取消全选
    Route::post('check-all', 'CartController/checkAll');

    // 删除购物车商品
    Route::post('delete', 'CartController/delete');

    // 清空购物车
    Route::post('clear', 'CartController/clear');

    // 获取购物车统计
    Route::get('count', 'CartController/count');
})->middleware(\app\middleware\Auth::class);

// 订单相关路由（需要登录）
Route::group('api/order', function () {
    // 创建订单
    Route::post('create', 'OrderController/create');

    // 获取订单列表
    Route::get('list', 'OrderController/list');

    // 获取订单详情
    Route::get('detail', 'OrderController/detail');

    // 支付订单
    Route::post('pay', 'OrderController/pay');

    // 取消订单
    Route::post('cancel', 'OrderController/cancel');

    // 确认收货
    Route::post('confirm', 'OrderController/confirm');

    // 删除订单
    Route::post('delete', 'OrderController/delete');

    // 获取订单统计
    Route::get('statistics', 'OrderController/statistics');
})->middleware(\app\middleware\Auth::class);
