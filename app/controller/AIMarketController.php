<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Order;
use app\model\Shop;
use app\common\Response;
use app\service\AIMarketService;
use think\facade\Db;

/**
 * AI行情预测控制器
 */
class AIMarketController extends BaseController
{
    /**
     * 获取AI行情预测
     */
    public function forecast()
    {
        try {
            $userId = $this->request->userId;

            // 获取商家店铺
            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            // 收集市场数据
            $marketData = $this->collectMarketData($shop);

            // 调用AI服务进行分析
            $aiService = new AIMarketService();
            $forecast = $aiService->analyzeMarket($marketData);

            return Response::success($forecast);
        } catch (\Exception $e) {
            \think\facade\Log::error('AI行情预测失败：' . $e->getMessage());
            return Response::error('AI分析失败：' . $e->getMessage());
        }
    }

    /**
     * 收集市场数据
     */
    private function collectMarketData($shop): array
    {
        $timeRange = 'month'; // 默认使用30天数据

        return [
            'shop_data' => [
                'sales_trend' => $this->getShopSalesTrend($shop->id, $timeRange),
                'product_rank' => $this->getShopProductRank($shop->id),
                'category_distribution' => $this->getShopCategoryDistribution($shop->id)
            ],
            'platform_data' => [
                'sales_trend' => $this->getPlatformSalesTrend($timeRange),
                'product_rank' => $this->getPlatformProductRank(),
                'category_distribution' => $this->getPlatformCategoryDistribution()
            ],
            'shop_info' => [
                'shop_name' => $shop->shop_name,
                'location' => $shop->location,
                'main_categories' => $this->getShopMainCategories($shop->id)
            ]
        ];
    }

    /**
     * 获取店铺销售趋势
     */
    private function getShopSalesTrend($shopId, $timeRange): array
    {
        $days = $this->getTimeRangeDays($timeRange);
        $dates = [];
        $sales = [];
        $orders = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dates[] = date('m-d', strtotime($date));

            $startTime = $date . ' 00:00:00';
            $endTime = $date . ' 23:59:59';

            $daySales = Order::where('shop_id', $shopId)
                ->where('created_at', '>=', $startTime)
                ->where('created_at', '<=', $endTime)
                ->whereIn('status', ['paid', 'shipped', 'completed'])
                ->sum('total_amount');

            $dayOrders = Order::where('shop_id', $shopId)
                ->where('created_at', '>=', $startTime)
                ->where('created_at', '<=', $endTime)
                ->count();

            $sales[] = floatval($daySales);
            $orders[] = $dayOrders;
        }

        return compact('dates', 'sales', 'orders');
    }

    /**
     * 获取平台销售趋势
     */
    private function getPlatformSalesTrend($timeRange): array
    {
        $days = $this->getTimeRangeDays($timeRange);
        $dates = [];
        $sales = [];
        $orders = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dates[] = date('m-d', strtotime($date));

            $startTime = $date . ' 00:00:00';
            $endTime = $date . ' 23:59:59';

            $daySales = Order::where('created_at', '>=', $startTime)
                ->where('created_at', '<=', $endTime)
                ->whereIn('status', ['paid', 'shipped', 'completed'])
                ->sum('total_amount');

            $dayOrders = Order::where('created_at', '>=', $startTime)
                ->where('created_at', '<=', $endTime)
                ->count();

            $sales[] = floatval($daySales);
            $orders[] = $dayOrders;
        }

        return compact('dates', 'sales', 'orders');
    }

    /**
     * 获取店铺热销产品排行
     */
    private function getShopProductRank($shopId): array
    {
        $products = Db::table('order_items')
            ->alias('oi')
            ->join('products p', 'oi.product_id = p.id')
            ->where('p.shop_id', $shopId)
            ->field('p.name as product_name, SUM(oi.quantity) as sales')
            ->group('oi.product_id')
            ->order('sales', 'desc')
            ->limit(10)
            ->select()
            ->toArray();

        return array_map(function ($item) {
            return [
                'name' => $item['product_name'],
                'sales' => intval($item['sales'])
            ];
        }, $products);
    }

    /**
     * 获取平台热销产品排行
     */
    private function getPlatformProductRank(): array
    {
        $products = Db::table('order_items')
            ->alias('oi')
            ->join('products p', 'oi.product_id = p.id')
            ->field('p.name as product_name, SUM(oi.quantity) as sales')
            ->group('oi.product_id')
            ->order('sales', 'desc')
            ->limit(10)
            ->select()
            ->toArray();

        return array_map(function ($item) {
            return [
                'name' => $item['product_name'],
                'sales' => intval($item['sales'])
            ];
        }, $products);
    }

    /**
     * 获取店铺品类分布
     */
    private function getShopCategoryDistribution($shopId): array
    {
        $categories = Db::table('order_items')
            ->alias('oi')
            ->join('products p', 'oi.product_id = p.id')
            ->join('categories c', 'p.category_id = c.id')
            ->where('p.shop_id', $shopId)
            ->field('c.name as category_name, SUM(oi.total_price) as value')
            ->group('p.category_id')
            ->order('value', 'desc')
            ->select()
            ->toArray();

        return array_map(function ($item) {
            return [
                'name' => $item['category_name'],
                'value' => floatval($item['value'])
            ];
        }, $categories);
    }

    /**
     * 获取平台品类分布
     */
    private function getPlatformCategoryDistribution(): array
    {
        $categories = Db::table('order_items')
            ->alias('oi')
            ->join('products p', 'oi.product_id = p.id')
            ->join('categories c', 'p.category_id = c.id')
            ->field('c.name as category_name, SUM(oi.total_price) as value')
            ->group('p.category_id')
            ->order('value', 'desc')
            ->select()
            ->toArray();

        return array_map(function ($item) {
            return [
                'name' => $item['category_name'],
                'value' => floatval($item['value'])
            ];
        }, $categories);
    }

    /**
     * 获取店铺主营品类
     */
    private function getShopMainCategories($shopId): array
    {
        $result = Db::table('products')
            ->alias('p')
            ->join('categories c', 'p.category_id = c.id')
            ->where('p.shop_id', $shopId)
            ->field('c.name')
            ->group('p.category_id')
            ->limit(3)
            ->select()
            ->toArray();

        return array_column($result, 'name');
    }

    /**
     * 根据时间范围获取天数
     */
    private function getTimeRangeDays($timeRange): int
    {
        switch ($timeRange) {
            case 'week':
                return 7;
            case 'month':
                return 30;
            case 'quarter':
                return 90;
            default:
                return 30;
        }
    }
}
