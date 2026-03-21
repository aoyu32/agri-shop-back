<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\Response;
use app\model\CommunityPost;
use app\model\Order;
use app\model\Product;
use app\model\Shop;
use app\model\User;

/**
 * 管理后台数据统计控制器
 */
class AdminDashboardController extends BaseController
{
    /**
     * 获取后台统计数据
     */
    public function overview()
    {
        try {
            $summary = [
                'total_users' => User::whereIn('role', ['consumer', 'merchant'])->count(),
                'total_consumers' => User::where('role', 'consumer')->count(),
                'total_merchants' => User::where('role', 'merchant')->count(),
                'total_shops' => Shop::count(),
                'approved_shops' => Shop::where('audit_status', 1)->count(),
                'pending_shop_audits' => Shop::where('audit_status', 0)->count(),
                'total_products' => Product::count(),
                'total_orders' => Order::count(),
                'total_gmv' => round((float) Order::whereIn('status', ['paid', 'shipped', 'completed'])->sum('actual_amount'), 2),
                'total_posts' => CommunityPost::count(),
            ];

            return Response::success([
                'summary' => $summary,
                'user_growth' => $this->buildUserGrowth(),
                'trade_trend' => $this->buildTradeTrend(),
                'product_distribution' => $this->buildProductDistribution(),
                'shop_audit_distribution' => [
                    ['name' => '待审核', 'value' => Shop::where('audit_status', 0)->count()],
                    ['name' => '已通过', 'value' => Shop::where('audit_status', 1)->count()],
                    ['name' => '已拒绝', 'value' => Shop::where('audit_status', 2)->count()],
                ],
            ]);
        } catch (\Exception $e) {
            return Response::error('获取统计数据失败：' . $e->getMessage());
        }
    }

    /**
     * 用户增长趋势
     */
    private function buildUserGrowth(): array
    {
        $dates = [];
        $consumerData = [];
        $merchantData = [];
        $totalData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} day"));
            $start = $date . ' 00:00:00';
            $end = $date . ' 23:59:59';

            $consumerCount = User::where('role', 'consumer')
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end)
                ->count();
            $merchantCount = User::where('role', 'merchant')
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end)
                ->count();

            $dates[] = date('m-d', strtotime($date));
            $consumerData[] = $consumerCount;
            $merchantData[] = $merchantCount;
            $totalData[] = $consumerCount + $merchantCount;
        }

        return [
            'dates' => $dates,
            'consumer' => $consumerData,
            'merchant' => $merchantData,
            'total' => $totalData,
        ];
    }

    /**
     * 交易趋势
     */
    private function buildTradeTrend(): array
    {
        $dates = [];
        $orderCount = [];
        $tradeAmount = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} day"));
            $start = $date . ' 00:00:00';
            $end = $date . ' 23:59:59';

            $dates[] = date('m-d', strtotime($date));
            $orderCount[] = Order::where('created_at', '>=', $start)
                ->where('created_at', '<=', $end)
                ->count();
            $tradeAmount[] = round((float) Order::where('created_at', '>=', $start)
                ->where('created_at', '<=', $end)
                ->whereIn('status', ['paid', 'shipped', 'completed'])
                ->sum('actual_amount'), 2);
        }

        return [
            'dates' => $dates,
            'order_count' => $orderCount,
            'trade_amount' => $tradeAmount,
        ];
    }

    /**
     * 商品分类分布
     */
    private function buildProductDistribution(): array
    {
        $categoryRows = \app\model\Category::select();
        $nameMap = $categoryRows->column('name', 'id');
        $parentMap = $categoryRows->column('parent_id', 'id');
        $products = Product::field('id,category_id')->select();

        $stats = [];
        foreach ($products as $product) {
            $categoryId = (int) $product->category_id;
            if (!$categoryId) {
                continue;
            }

            $topCategoryId = $categoryId;
            if (($parentMap[$categoryId] ?? 0) > 0) {
                $topCategoryId = (int) $parentMap[$categoryId];
            }

            $name = $nameMap[$topCategoryId] ?? ($nameMap[$categoryId] ?? '未分类');
            $stats[$name] = ($stats[$name] ?? 0) + 1;
        }

        $result = [];
        foreach ($stats as $name => $value) {
            $result[] = [
                'name' => $name,
                'value' => $value,
            ];
        }

        usort($result, function ($a, $b) {
            return $b['value'] <=> $a['value'];
        });

        return $result;
    }
}
