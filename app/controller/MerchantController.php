<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Order;
use app\model\Product;
use app\model\OrderReview;
use app\model\Shop;
use app\common\Response;

/**
 * 商家管理控制器
 */
class MerchantController extends BaseController
{
    /**
     * 获取商家数据概览
     */
    public function dashboard()
    {
        try {
            $userId = $this->request->userId;

            // 获取商家店铺
            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            // 今日日期
            $today = date('Y-m-d');
            $todayStart = $today . ' 00:00:00';
            $todayEnd = $today . ' 23:59:59';

            // 昨日日期
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $yesterdayStart = $yesterday . ' 00:00:00';
            $yesterdayEnd = $yesterday . ' 23:59:59';

            // 今日订单数
            $todayOrders = Order::where('shop_id', $shop->id)
                ->where('created_at', '>=', $todayStart)
                ->where('created_at', '<=', $todayEnd)
                ->count();

            // 昨日订单数
            $yesterdayOrders = Order::where('shop_id', $shop->id)
                ->where('created_at', '>=', $yesterdayStart)
                ->where('created_at', '<=', $yesterdayEnd)
                ->count();

            // 今日销售额
            $todaySales = Order::where('shop_id', $shop->id)
                ->where('created_at', '>=', $todayStart)
                ->where('created_at', '<=', $todayEnd)
                ->whereIn('status', ['paid', 'shipped', 'completed'])
                ->sum('total_amount');

            // 昨日销售额
            $yesterdaySales = Order::where('shop_id', $shop->id)
                ->where('created_at', '>=', $yesterdayStart)
                ->where('created_at', '<=', $yesterdayEnd)
                ->whereIn('status', ['paid', 'shipped', 'completed'])
                ->sum('total_amount');

            // 总订单数
            $totalOrders = Order::where('shop_id', $shop->id)->count();

            // 总销售额
            $totalSales = Order::where('shop_id', $shop->id)
                ->whereIn('status', ['paid', 'shipped', 'completed'])
                ->sum('total_amount');

            // 农产品总数
            $totalProducts = Product::where('shop_id', $shop->id)->count();

            // 在售农产品数
            $onSaleProducts = Product::where('shop_id', $shop->id)
                ->where('status', 'on_sale')
                ->count();

            // 待回复评价数
            $pendingReviews = OrderReview::where('shop_id', $shop->id)
                ->where('reply_content', 'null')
                ->count();

            // 总评价数
            $totalReviews = OrderReview::where('shop_id', $shop->id)->count();

            // 待发货订单数
            $toShipOrders = Order::where('shop_id', $shop->id)
                ->where('status', 'paid')
                ->count();

            // 库存预警（库存小于10的商品）
            $lowStockProducts = Product::where('shop_id', $shop->id)
                ->where('stock', '<', 10)
                ->where('status', 'on_sale')
                ->count();

            // 计算增长率
            $ordersGrowth = $yesterdayOrders > 0
                ? round((($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100, 1)
                : 0;

            $salesGrowth = $yesterdaySales > 0
                ? round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1)
                : 0;

            return Response::success([
                'stats' => [
                    'today_orders' => $todayOrders,
                    'today_sales' => number_format($todaySales, 2, '.', ''),
                    'total_orders' => $totalOrders,
                    'total_sales' => number_format($totalSales, 2, '.', ''),
                    'total_products' => $totalProducts,
                    'on_sale_products' => $onSaleProducts,
                    'pending_reviews' => $pendingReviews,
                    'total_reviews' => $totalReviews
                ],
                'pending_tasks' => [
                    'to_ship' => $toShipOrders,
                    'to_reply' => $pendingReviews,
                    'low_stock' => $lowStockProducts
                ]
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取商家数据概览失败：' . $e->getMessage());
            return Response::error('获取数据失败：' . $e->getMessage());
        }
    }
}
