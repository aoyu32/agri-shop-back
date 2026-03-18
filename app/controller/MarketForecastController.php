<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Order;
use app\model\OrderItem;
use app\model\Product;
use app\model\Category;
use app\model\Shop;
use app\common\Response;
use think\facade\Db;

/**
 * 行情预测控制器
 */
class MarketForecastController extends BaseController
{
    /**
     * 获取店铺销售趋势数据
     */
    public function shopSalesTrend()
    {
        try {
            $userId = $this->request->userId;
            $timeRange = $this->request->param('time_range', 'month'); // week, month, quarter

            // 获取商家店铺
            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            // 根据时间范围计算日期
            $days = $this->getTimeRangeDays($timeRange);
            $dates = [];
            $sales = [];
            $orders = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $dates[] = date('m-d', strtotime($date));

                $startTime = $date . ' 00:00:00';
                $endTime = $date . ' 23:59:59';

                // 当日销售额
                $daySales = Order::where('shop_id', $shop->id)
                    ->where('created_at', '>=', $startTime)
                    ->where('created_at', '<=', $endTime)
                    ->whereIn('status', ['paid', 'shipped', 'completed'])
                    ->sum('total_amount');

                // 当日订单量
                $dayOrders = Order::where('shop_id', $shop->id)
                    ->where('created_at', '>=', $startTime)
                    ->where('created_at', '<=', $endTime)
                    ->count();

                $sales[] = floatval($daySales);
                $orders[] = $dayOrders;
            }

            return Response::success([
                'week' => $timeRange === 'week' ? compact('dates', 'sales', 'orders') : null,
                'month' => $timeRange === 'month' ? compact('dates', 'sales', 'orders') : null,
                'quarter' => $timeRange === 'quarter' ? compact('dates', 'sales', 'orders') : null
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取店铺销售趋势失败：' . $e->getMessage());
            return Response::error('获取数据失败：' . $e->getMessage());
        }
    }

    /**
     * 获取平台销售趋势数据
     */
    public function platformSalesTrend()
    {
        try {
            $timeRange = $this->request->param('time_range', 'month');

            $days = $this->getTimeRangeDays($timeRange);
            $dates = [];
            $sales = [];
            $orders = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $dates[] = date('m-d', strtotime($date));

                $startTime = $date . ' 00:00:00';
                $endTime = $date . ' 23:59:59';

                // 当日销售额
                $daySales = Order::where('created_at', '>=', $startTime)
                    ->where('created_at', '<=', $endTime)
                    ->whereIn('status', ['paid', 'shipped', 'completed'])
                    ->sum('total_amount');

                // 当日订单量
                $dayOrders = Order::where('created_at', '>=', $startTime)
                    ->where('created_at', '<=', $endTime)
                    ->count();

                $sales[] = floatval($daySales);
                $orders[] = $dayOrders;
            }

            return Response::success([
                'week' => $timeRange === 'week' ? compact('dates', 'sales', 'orders') : null,
                'month' => $timeRange === 'month' ? compact('dates', 'sales', 'orders') : null,
                'quarter' => $timeRange === 'quarter' ? compact('dates', 'sales', 'orders') : null
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取平台销售趋势失败：' . $e->getMessage());
            return Response::error('获取数据失败：' . $e->getMessage());
        }
    }

    /**
     * 获取店铺热销农产品排行
     */
    public function shopProductRank()
    {
        try {
            $userId = $this->request->userId;

            // 获取商家店铺
            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            // 查询热销农产品（按销量排序，取前10）
            $products = Db::table('order_items')
                ->alias('oi')
                ->join('products p', 'oi.product_id = p.id')
                ->where('p.shop_id', $shop->id)
                ->field('p.name, SUM(oi.quantity) as sales')
                ->group('oi.product_id')
                ->order('sales', 'desc')
                ->limit(10)
                ->select()
                ->toArray();

            $result = array_map(function ($item) {
                return [
                    'name' => $item['name'],
                    'sales' => intval($item['sales'])
                ];
            }, $products);

            return Response::success($result);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取店铺热销农产品失败：' . $e->getMessage());
            return Response::error('获取数据失败：' . $e->getMessage());
        }
    }

    /**
     * 获取平台热销农产品排行
     */
    public function platformProductRank()
    {
        try {
            // 查询热销农产品（按销量排序，取前10）
            $products = Db::table('order_items')
                ->alias('oi')
                ->join('products p', 'oi.product_id = p.id')
                ->field('p.name, SUM(oi.quantity) as sales')
                ->group('oi.product_id')
                ->order('sales', 'desc')
                ->limit(10)
                ->select()
                ->toArray();

            $result = array_map(function ($item) {
                return [
                    'name' => $item['name'],
                    'sales' => intval($item['sales'])
                ];
            }, $products);

            return Response::success($result);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取平台热销农产品失败：' . $e->getMessage());
            return Response::error('获取数据失败：' . $e->getMessage());
        }
    }

    /**
     * 获取店铺品类销售分布
     */
    public function shopCategoryDistribution()
    {
        try {
            $userId = $this->request->userId;

            // 获取商家店铺
            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            // 查询品类销售分布
            $categories = Db::table('order_items')
                ->alias('oi')
                ->join('products p', 'oi.product_id = p.id')
                ->join('categories c', 'p.category_id = c.id')
                ->where('p.shop_id', $shop->id)
                ->field('c.name, SUM(oi.total_price) as value')
                ->group('p.category_id')
                ->order('value', 'desc')
                ->select()
                ->toArray();

            $result = array_map(function ($item) {
                return [
                    'name' => $item['name'],
                    'value' => floatval($item['value'])
                ];
            }, $categories);

            return Response::success($result);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取店铺品类分布失败：' . $e->getMessage());
            return Response::error('获取数据失败：' . $e->getMessage());
        }
    }

    /**
     * 获取平台品类销售分布
     */
    public function platformCategoryDistribution()
    {
        try {
            // 查询品类销售分布
            $categories = Db::table('order_items')
                ->alias('oi')
                ->join('products p', 'oi.product_id = p.id')
                ->join('categories c', 'p.category_id = c.id')
                ->field('c.name, SUM(oi.total_price) as value')
                ->group('p.category_id')
                ->order('value', 'desc')
                ->select()
                ->toArray();

            $result = array_map(function ($item) {
                return [
                    'name' => $item['name'],
                    'value' => floatval($item['value'])
                ];
            }, $categories);

            return Response::success($result);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取平台品类分布失败：' . $e->getMessage());
            return Response::error('获取数据失败：' . $e->getMessage());
        }
    }

    /**
     * 根据时间范围获取天数
     */
    private function getTimeRangeDays($timeRange)
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
