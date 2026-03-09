<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Footprint;
use app\model\Product;
use app\common\Response;
use think\facade\Db;

/**
 * 浏览足迹控制器
 */
class FootprintController extends BaseController
{
    /**
     * 添加浏览记录
     */
    public function add()
    {
        try {
            $userId = $this->request->userId;
            $productId = (int)$this->request->param('product_id');

            if (!$productId) {
                return Response::validateError('商品ID不能为空');
            }

            // 检查商品是否存在
            $product = Product::find($productId);
            if (!$product) {
                return Response::error('商品不存在');
            }

            // 检查是否已有浏览记录
            $footprint = Footprint::where('user_id', $userId)
                ->where('product_id', $productId)
                ->find();

            if ($footprint) {
                // 已有记录，更新浏览次数和最后浏览时间
                $footprint->view_count += 1;
                $footprint->last_view_time = date('Y-m-d H:i:s');
                $footprint->save();
            } else {
                // 新增浏览记录
                Footprint::create([
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'view_count' => 1,
                    'last_view_time' => date('Y-m-d H:i:s')
                ]);
            }

            return Response::success([], '记录成功');
        } catch (\Exception $e) {
            return Response::error('记录失败：' . $e->getMessage());
        }
    }

    /**
     * 获取浏览足迹列表
     */
    public function list()
    {
        try {
            $userId = $this->request->userId;
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);

            $query = Footprint::where('user_id', $userId)
                ->with(['product' => function ($query) {
                    $query->field('id,name,subtitle,main_image,price,original_price,unit,sales,stock,status');
                }]);

            $total = $query->count();
            $list = $query->order('last_view_time', 'desc')
                ->page($page, $limit)
                ->select();

            // 格式化数据
            $footprints = [];
            foreach ($list as $item) {
                if ($item->product) {
                    $footprints[] = [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'product_subtitle' => $item->product->subtitle,
                        'product_image' => $item->product->main_image,
                        'price' => (float)$item->product->price,
                        'original_price' => $item->product->original_price ? (float)$item->product->original_price : null,
                        'unit' => $item->product->unit,
                        'sales' => $item->product->sales,
                        'stock' => $item->product->stock,
                        'status' => $item->product->status,
                        'view_count' => $item->view_count,
                        'last_view_time' => $item->last_view_time,
                        'created_at' => $item->created_at
                    ];
                }
            }

            return Response::success([
                'list' => $footprints,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]);
        } catch (\Exception $e) {
            return Response::error('获取足迹列表失败：' . $e->getMessage());
        }
    }

    /**
     * 删除单条足迹
     */
    public function delete()
    {
        try {
            $userId = $this->request->userId;
            $id = (int)$this->request->param('id');

            if (!$id) {
                return Response::validateError('足迹ID不能为空');
            }

            // 删除足迹
            $result = Footprint::where('id', $id)
                ->where('user_id', $userId)
                ->delete();

            if ($result) {
                return Response::success([], '删除成功');
            } else {
                return Response::error('未找到足迹记录');
            }
        } catch (\Exception $e) {
            return Response::error('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 批量删除足迹
     */
    public function batchDelete()
    {
        try {
            $userId = $this->request->userId;
            $ids = $this->request->param('ids'); // 足迹ID数组

            if (empty($ids) || !is_array($ids)) {
                return Response::validateError('请选择要删除的足迹');
            }

            // 删除足迹
            $result = Footprint::where('user_id', $userId)
                ->whereIn('id', $ids)
                ->delete();

            return Response::success([
                'count' => $result
            ], '删除成功');
        } catch (\Exception $e) {
            return Response::error('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 清空所有足迹
     */
    public function clear()
    {
        try {
            $userId = $this->request->userId;

            // 删除该用户的所有足迹
            $result = Footprint::where('user_id', $userId)->delete();

            return Response::success([
                'count' => $result
            ], '清空成功');
        } catch (\Exception $e) {
            return Response::error('清空失败：' . $e->getMessage());
        }
    }

    /**
     * 按日期分组获取足迹
     */
    public function listByDate()
    {
        try {
            $userId = $this->request->userId;
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 50);

            $query = Footprint::where('user_id', $userId)
                ->with(['product' => function ($query) {
                    $query->field('id,name,subtitle,main_image,price,original_price,unit,sales,stock,status');
                }]);

            $total = $query->count();
            $list = $query->order('last_view_time', 'desc')
                ->page($page, $limit)
                ->select();

            // 按日期分组
            $groupedData = [];
            foreach ($list as $item) {
                if ($item->product) {
                    $date = date('Y-m-d', strtotime($item->last_view_time));
                    $dateLabel = $this->getDateLabel($date);

                    if (!isset($groupedData[$date])) {
                        $groupedData[$date] = [
                            'date' => $date,
                            'date_label' => $dateLabel,
                            'items' => []
                        ];
                    }

                    $groupedData[$date]['items'][] = [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'product_subtitle' => $item->product->subtitle,
                        'product_image' => $item->product->main_image,
                        'price' => (float)$item->product->price,
                        'original_price' => $item->product->original_price ? (float)$item->product->original_price : null,
                        'unit' => $item->product->unit,
                        'sales' => $item->product->sales,
                        'stock' => $item->product->stock,
                        'status' => $item->product->status,
                        'view_count' => $item->view_count,
                        'last_view_time' => $item->last_view_time
                    ];
                }
            }

            // 转换为数组
            $result = array_values($groupedData);

            return Response::success([
                'list' => $result,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]);
        } catch (\Exception $e) {
            return Response::error('获取足迹列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取足迹统计
     */
    public function statistics()
    {
        try {
            $userId = $this->request->userId;

            $total = Footprint::where('user_id', $userId)->count();
            $today = Footprint::where('user_id', $userId)
                ->whereTime('last_view_time', 'today')
                ->count();
            $week = Footprint::where('user_id', $userId)
                ->whereTime('last_view_time', 'week')
                ->count();

            return Response::success([
                'total' => $total,
                'today' => $today,
                'week' => $week
            ]);
        } catch (\Exception $e) {
            return Response::error('获取统计失败：' . $e->getMessage());
        }
    }

    /**
     * 获取日期标签
     */
    private function getDateLabel($date): string
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        if ($date === $today) {
            return '今天';
        } elseif ($date === $yesterday) {
            return '昨天';
        } else {
            return $date;
        }
    }
}
