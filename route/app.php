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

    // 获取店铺页面（包含分类和商品）
    Route::get('page', 'ShopController/shopPage');

    // 申请开通店铺（需要登录）
    Route::post('apply', 'ShopController/apply')->middleware(\app\middleware\Auth::class);

    // 获取我的店铺信息（需要登录）
    Route::get('my-shop', 'ShopController/myShop')->middleware(\app\middleware\Auth::class);

    // 更新店铺设置（需要登录）
    Route::post('update-settings', 'ShopController/updateSettings')->middleware(\app\middleware\Auth::class);
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

// 收货地址相关路由（需要登录）
Route::group('api/address', function () {
    // 获取地址列表
    Route::get('list', 'AddressController/index');

    // 获取地址详情
    Route::get('detail/:id', 'AddressController/read');

    // 添加地址
    Route::post('add', 'AddressController/save');

    // 更新地址
    Route::post('update/:id', 'AddressController/update');

    // 删除地址
    Route::post('delete/:id', 'AddressController/delete');

    // 设置默认地址
    Route::post('set-default/:id', 'AddressController/setDefault');

    // 获取默认地址
    Route::get('default', 'AddressController/getDefault');
})->middleware(\app\middleware\Auth::class);

// 收藏相关路由（需要登录）
Route::group('api/favorite', function () {
    // 添加收藏
    Route::post('add', 'FavoriteController/add');

    // 取消收藏
    Route::post('remove', 'FavoriteController/remove');

    // 切换收藏状态
    Route::post('toggle', 'FavoriteController/toggle');

    // 获取收藏列表
    Route::get('list', 'FavoriteController/list');

    // 检查是否已收藏
    Route::get('check', 'FavoriteController/check');

    // 批量删除收藏
    Route::post('batch-remove', 'FavoriteController/batchRemove');

    // 获取收藏统计
    Route::get('statistics', 'FavoriteController/statistics');
})->middleware(\app\middleware\Auth::class);

// 浏览足迹相关路由（需要登录）
Route::group('api/footprint', function () {
    // 添加浏览记录
    Route::post('add', 'FootprintController/add');

    // 获取足迹列表
    Route::get('list', 'FootprintController/list');

    // 按日期分组获取足迹
    Route::get('list-by-date', 'FootprintController/listByDate');

    // 删除单条足迹
    Route::post('delete', 'FootprintController/delete');

    // 批量删除足迹
    Route::post('batch-delete', 'FootprintController/batchDelete');

    // 清空所有足迹
    Route::post('clear', 'FootprintController/clear');

    // 获取足迹统计
    Route::get('statistics', 'FootprintController/statistics');
})->middleware(\app\middleware\Auth::class);

// OSS文件管理相关路由（需要登录）
Route::group('api/oss', function () {
    // 上传单个文件
    Route::post('upload', 'OssController/upload');

    // 批量上传文件
    Route::post('batch-upload', 'OssController/batchUpload');

    // 上传Base64图片
    Route::post('upload-base64', 'OssController/uploadBase64');

    // 删除文件
    Route::post('delete', 'OssController/delete');

    // 批量删除文件
    Route::post('batch-delete', 'OssController/batchDelete');

    // 获取文件列表
    Route::get('list', 'OssController/list');

    // 获取文件信息
    Route::get('info', 'OssController/info');

    // 获取签名URL
    Route::get('signed-url', 'OssController/getSignedUrl');

    // 复制文件
    Route::post('copy', 'OssController/copy');
})->middleware(\app\middleware\Auth::class);

// 用户信息管理相关路由（需要登录）
Route::group('api/user', function () {
    // 获取用户信息
    Route::get('info', 'UserController/info');

    // 更新用户信息
    Route::post('update', 'UserController/update');

    // 更新头像
    Route::post('update-avatar', 'UserController/updateAvatar');

    // 修改密码
    Route::post('change-password', 'UserController/changePassword');

    // 发送验证码
    Route::post('send-code', 'UserController/sendCode');

    // 更换手机号
    Route::post('change-phone', 'UserController/changePhone');

    // 注销账号
    Route::post('delete-account', 'UserController/deleteAccount');

    // 获取用户统计信息
    Route::get('statistics', 'UserController/statistics');

    // 获取首页统计数据
    Route::get('home-statistics', 'UserController/homeStatistics');
})->middleware(\app\middleware\Auth::class);

// 当季农产品相关路由
Route::group('api/seasonal-product', function () {
    // 获取当季农产品列表
    Route::get('list', 'SeasonalProductController/list');

    // 获取所有季节的农产品
    Route::get('all', 'SeasonalProductController/all');
});

// 商家管理相关路由（需要登录）
Route::group('api/merchant', function () {
    // 获取数据概览
    Route::get('dashboard', 'MerchantController/dashboard');
})->middleware(\app\middleware\Auth::class);

// 行情预测相关路由
Route::group('api/market-forecast', function () {
    // 获取店铺销售趋势
    Route::get('shop-sales-trend', 'MarketForecastController/shopSalesTrend');

    // 获取平台销售趋势
    Route::get('platform-sales-trend', 'MarketForecastController/platformSalesTrend');

    // 获取店铺热销农产品排行
    Route::get('shop-product-rank', 'MarketForecastController/shopProductRank');

    // 获取平台热销农产品排行
    Route::get('platform-product-rank', 'MarketForecastController/platformProductRank');

    // 获取店铺品类销售分布
    Route::get('shop-category-distribution', 'MarketForecastController/shopCategoryDistribution');

    // 获取平台品类销售分布
    Route::get('platform-category-distribution', 'MarketForecastController/platformCategoryDistribution');
})->middleware(\app\middleware\Auth::class);

// AI行情预测相关路由
Route::group('api/ai-market', function () {
    // 获取AI行情预测
    Route::get('forecast', 'AIMarketController/forecast');
})->middleware(\app\middleware\Auth::class);


// 农户订单管理相关路由（需要登录且为农户角色）
Route::group('api/merchant/order', function () {
    // 获取订单列表
    Route::get('list', 'MerchantOrderController/list');

    // 获取订单详情
    Route::get('detail', 'MerchantOrderController/detail');

    // 发货
    Route::post('ship', 'MerchantOrderController/ship');

    // 删除订单
    Route::post('delete', 'MerchantOrderController/delete');

    // 获取订单统计
    Route::get('statistics', 'MerchantOrderController/statistics');

    // 获取退款列表
    Route::get('refund-list', 'MerchantOrderController/refundList');

    // 获取退款详情
    Route::get('refund-detail', 'MerchantOrderController/refundDetail');

    // 同意退款
    Route::post('approve-refund', 'MerchantOrderController/approveRefund');

    // 拒绝退款
    Route::post('reject-refund', 'MerchantOrderController/rejectRefund');

    // 确认退款完成
    Route::post('confirm-refund', 'MerchantOrderController/confirmRefund');

    // 删除退款记录
    Route::post('delete-refund', 'MerchantOrderController/deleteRefund');
})->middleware(\app\middleware\Auth::class);

// 农户分类管理相关路由（需要登录且为农户角色）
Route::group('api/merchant/category', function () {
    // 获取系统分类列表（供选择）
    Route::get('system-list', 'MerchantCategoryController/systemCategories');

    // 获取店铺分类列表
    Route::get('list', 'MerchantCategoryController/list');

    // 添加分类到店铺
    Route::post('add', 'MerchantCategoryController/add');

    // 更新分类排序
    Route::post('update', 'MerchantCategoryController/update');

    // 切换分类状态
    Route::post('toggle-status', 'MerchantCategoryController/toggleStatus');

    // 删除店铺分类
    Route::post('delete', 'MerchantCategoryController/delete');
})->middleware(\app\middleware\Auth::class);

// 农户商品管理相关路由（需要登录且为农户角色）
Route::group('api/merchant/product', function () {
    // 获取商品列表
    Route::get('list', 'MerchantProductController/list');

    // 获取商品详情
    Route::get('detail', 'MerchantProductController/detail');

    // 添加商品
    Route::post('add', 'MerchantProductController/add');

    // 更新商品
    Route::post('update', 'MerchantProductController/update');

    // 切换商品状态
    Route::post('toggle-status', 'MerchantProductController/toggleStatus');

    // 删除商品
    Route::post('delete', 'MerchantProductController/delete');
})->middleware(\app\middleware\Auth::class);

// 评价相关路由
Route::group('api/review', function () {
    // 提交订单评价（需要登录）
    Route::post('submit', 'ReviewController/submit')->middleware(\app\middleware\Auth::class);

    // 获取待评价订单列表（需要登录）
    Route::get('pending-list', 'ReviewController/pendingList')->middleware(\app\middleware\Auth::class);

    // 获取我的评价列表（需要登录）
    Route::get('my-reviews', 'ReviewController/myReviews')->middleware(\app\middleware\Auth::class);

    // 删除评价（需要登录）
    Route::post('delete', 'ReviewController/deleteReview')->middleware(\app\middleware\Auth::class);

    // 点赞评价（需要登录）
    Route::post('like', 'ReviewController/likeReview')->middleware(\app\middleware\Auth::class);

    // 取消点赞评价（需要登录）
    Route::post('unlike', 'ReviewController/unlikeReview')->middleware(\app\middleware\Auth::class);

    // 获取商品评价列表（公开接口，不需要登录）
    Route::get('product-reviews', 'ReviewController/productReviews');

    // 商家获取评价列表（需要登录）
    Route::get('merchant-reviews', 'ReviewController/merchantReviews')->middleware(\app\middleware\Auth::class);

    // 商家回复评价（需要登录）
    Route::post('merchant-reply', 'ReviewController/merchantReply')->middleware(\app\middleware\Auth::class);

    // 商家删除回复（需要登录）
    Route::post('merchant-delete-reply', 'ReviewController/merchantDeleteReply')->middleware(\app\middleware\Auth::class);
});

// 退款相关路由
Route::group('api/refund', function () {
    // 申请退款（需要登录）
    Route::post('apply', 'RefundController/apply')->middleware(\app\middleware\Auth::class);

    // 获取我的退款列表（需要登录）
    Route::get('my-list', 'RefundController/myList')->middleware(\app\middleware\Auth::class);

    // 获取退款详情（需要登录）
    Route::get('detail', 'RefundController/detail')->middleware(\app\middleware\Auth::class);

    // 取消退款申请（需要登录）
    Route::post('cancel', 'RefundController/cancel')->middleware(\app\middleware\Auth::class);
});

// AI咨询相关路由（需要登录）
Route::group('api/ai-consult', function () {
    // 发送消息（流式输出）
    Route::post('send-message-stream', 'AiConsultController/sendMessageStream');

    // 获取会话列表
    Route::get('conversations', 'AiConsultController/getConversations');

    // 获取会话消息列表
    Route::get('messages', 'AiConsultController/getMessages');

    // 删除会话
    Route::post('delete-conversation', 'AiConsultController/deleteConversation');

    // 更新会话标题
    Route::post('update-title', 'AiConsultController/updateConversationTitle');

    // 清空所有会话
    Route::post('clear-all', 'AiConsultController/clearAllConversations');
})->middleware(\app\middleware\Auth::class);
