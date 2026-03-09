<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Favorite;
use app\model\Product;
use app\common\Response;

/**
 * 收藏控制器
 */
class FavoriteController extends BaseController
{
    /**
     * 添加收藏
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

            // 检查是否已收藏
            $exists = Favorite::where('user_id', $userId)
                ->where('product_id', $productId)
                ->find();

            if ($exists) {
                return Response::error('已经收藏过该商品');
            }

            // 添加收藏
            Favorite::create([
                'user_id' => $userId,
                'product_id' => $productId
            ]);

            return Response::success([], '收藏成功');
        } catch (\Exception $e) {
            return Response::error('收藏失败：' . $e->getMessage());
        }
    }

    /**
     * 取消收藏
     */
    public function remove()
    {
        try {
            $userId = $this->request->userId;
            $productId = (int)$this->request->param('product_id');

            if (!$productId) {
                return Response::validateError('商品ID不能为空');
            }

            // 删除收藏
            $result = Favorite::where('user_id', $userId)
                ->where('product_id', $productId)
                ->delete();

            if ($result) {
                return Response::success([], '取消收藏成功');
            } else {
                return Response::error('未找到收藏记录');
            }
        } catch (\Exception $e) {
            return Response::error('取消收藏失败：' . $e->getMessage());
        }
    }

    /**
     * 切换收藏状态（收藏/取消收藏）
     */
    public function toggle()
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

            // 检查是否已收藏
            $favorite = Favorite::where('user_id', $userId)
                ->where('product_id', $productId)
                ->find();

            if ($favorite) {
                // 已收藏，则取消收藏
                $favorite->delete();
                return Response::success([
                    'is_favorite' => false
                ], '取消收藏成功');
            } else {
                // 未收藏，则添加收藏
                Favorite::create([
                    'user_id' => $userId,
                    'product_id' => $productId
                ]);
                return Response::success([
                    'is_favorite' => true
                ], '收藏成功');
            }
        } catch (\Exception $e) {
            return Response::error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 获取收藏列表
     */
    public function list()
    {
        try {
            $userId = $this->request->userId;
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);

            $query = Favorite::where('user_id', $userId)
                ->with(['product' => function ($query) {
                    $query->field('id,name,subtitle,main_image,price,original_price,unit,sales,stock,status');
                }]);

            $total = $query->count();
            $list = $query->order('created_at', 'desc')
                ->page($page, $limit)
                ->select();

            // 格式化数据
            $favorites = [];
            foreach ($list as $item) {
                if ($item->product) {
                    $favorites[] = [
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
                        'created_at' => $item->created_at
                    ];
                }
            }

            return Response::success([
                'list' => $favorites,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]);
        } catch (\Exception $e) {
            return Response::error('获取收藏列表失败：' . $e->getMessage());
        }
    }

    /**
     * 检查商品是否已收藏
     */
    public function check()
    {
        try {
            $userId = $this->request->userId;
            $productId = (int)$this->request->param('product_id');

            if (!$productId) {
                return Response::validateError('商品ID不能为空');
            }

            $exists = Favorite::where('user_id', $userId)
                ->where('product_id', $productId)
                ->find();

            return Response::success([
                'is_favorite' => $exists ? true : false
            ]);
        } catch (\Exception $e) {
            return Response::error('检查失败：' . $e->getMessage());
        }
    }

    /**
     * 批量删除收藏
     */
    public function batchRemove()
    {
        try {
            $userId = $this->request->userId;
            $ids = $this->request->param('ids'); // 收藏ID数组

            if (empty($ids) || !is_array($ids)) {
                return Response::validateError('请选择要删除的收藏');
            }

            // 删除收藏
            $result = Favorite::where('user_id', $userId)
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
     * 获取收藏统计
     */
    public function statistics()
    {
        try {
            $userId = $this->request->userId;

            $total = Favorite::where('user_id', $userId)->count();

            return Response::success([
                'total' => $total
            ]);
        } catch (\Exception $e) {
            return Response::error('获取统计失败：' . $e->getMessage());
        }
    }
}
