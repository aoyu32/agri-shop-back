<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Order;
use app\model\OrderItem;
use app\model\OrderReview;
use app\model\Product;
use app\common\Response;

/**
 * 订单评价控制器
 */
class ReviewController extends BaseController
{
    /**
     * 提交订单评价
     */
    public function submit()
    {
        try {
            $userId = $this->request->userId;
            $orderId = $this->request->param('order_id');
            $reviews = $this->request->param('reviews'); // 评价列表

            if (!$orderId) {
                return Response::validateError('订单ID不能为空');
            }

            if (!$reviews || !is_array($reviews) || empty($reviews)) {
                return Response::validateError('请提交评价内容');
            }

            // 验证订单
            $order = Order::where('id', $orderId)
                ->where('user_id', $userId)
                ->find();

            if (!$order) {
                return Response::error('订单不存在');
            }

            if ($order->status !== 'completed') {
                return Response::error('只有已完成的订单才能评价');
            }

            if ($order->is_reviewed) {
                return Response::error('该订单已评价，不能重复评价');
            }

            // 获取订单商品
            $orderItems = OrderItem::where('order_id', $orderId)->select();
            $itemIds = array_column($orderItems->toArray(), 'id');

            // 开始事务
            Order::startTrans();
            try {
                foreach ($reviews as $review) {
                    // 验证必填字段
                    if (!isset($review['order_item_id']) || !isset($review['rating'])) {
                        Order::rollback();
                        return Response::validateError('评价数据不完整');
                    }

                    // 验证订单商品ID
                    if (!in_array($review['order_item_id'], $itemIds)) {
                        Order::rollback();
                        return Response::error('订单商品ID不正确');
                    }

                    // 验证评分
                    if ($review['rating'] < 1 || $review['rating'] > 5) {
                        Order::rollback();
                        return Response::validateError('评分必须在1-5之间');
                    }

                    // 获取订单商品信息
                    $orderItem = OrderItem::where('id', $review['order_item_id'])->find();

                    // 处理图片数据
                    $images = $review['images'] ?? [];
                    if (!is_array($images)) {
                        $images = [];
                    }

                    // 记录日志用于调试
                    \think\facade\Log::info('创建评价 - 图片数据:', [
                        'order_item_id' => $review['order_item_id'],
                        'images' => $images,
                        'images_count' => count($images)
                    ]);

                    // 创建评价
                    OrderReview::create([
                        'order_id' => $orderId,
                        'user_id' => $userId,
                        'shop_id' => $order->shop_id,
                        'product_id' => $orderItem->product_id,
                        'order_item_id' => $review['order_item_id'],
                        'rating' => $review['rating'],
                        'content' => $review['content'] ?? '',
                        'images' => $images,
                        'is_anonymous' => $review['is_anonymous'] ?? 0
                    ]);

                    // 更新商品评分
                    $this->updateProductRating($orderItem->product_id);
                }

                // 标记订单已评价
                $order->is_reviewed = 1;
                $order->save();

                Order::commit();
                return Response::success([], '评价成功');
            } catch (\Exception $e) {
                Order::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            \think\facade\Log::error('提交评价失败：' . $e->getMessage());
            return Response::error('提交评价失败：' . $e->getMessage());
        }
    }

    /**
     * 获取待评价订单列表
     */
    public function pendingList()
    {
        try {
            $userId = $this->request->userId;
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 10);

            // 查询已完成但未评价的订单
            $query = Order::with(['items', 'shop'])
                ->where('user_id', $userId)
                ->where('status', 'completed')
                ->where('is_reviewed', 0)
                ->order('complete_time', 'desc');

            $orders = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page
            ]);

            $list = [];
            foreach ($orders->items() as $order) {
                $list[] = $this->formatOrderForReview($order);
            }

            return Response::success([
                'list' => $list,
                'total' => $orders->total(),
                'page' => $page,
                'page_size' => $pageSize
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取待评价订单失败：' . $e->getMessage());
            return Response::error('获取待评价订单失败：' . $e->getMessage());
        }
    }

    /**
     * 获取商品评价列表
     */
    public function productReviews()
    {
        try {
            $productId = $this->request->param('product_id');
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 10);

            if (!$productId) {
                return Response::validateError('商品ID不能为空');
            }

            $query = OrderReview::with(['user'])
                ->where('product_id', $productId)
                ->order('created_at', 'desc');

            $reviews = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page
            ]);

            $list = [];
            foreach ($reviews->items() as $review) {
                $list[] = $this->formatReview($review);
            }

            return Response::success([
                'list' => $list,
                'total' => $reviews->total(),
                'page' => $page,
                'page_size' => $pageSize
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取商品评价失败：' . $e->getMessage());
            return Response::error('获取商品评价失败：' . $e->getMessage());
        }
    }

    /**
     * 获取我的评价列表
     */
    public function myReviews()
    {
        try {
            $userId = $this->request->userId;
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 10);

            $query = OrderReview::with(['product', 'shop'])
                ->where('user_id', $userId)
                ->order('created_at', 'desc');

            $reviews = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page
            ]);

            $list = [];
            foreach ($reviews->items() as $review) {
                $list[] = $this->formatMyReview($review);
            }

            return Response::success([
                'list' => $list,
                'total' => $reviews->total(),
                'page' => $page,
                'page_size' => $pageSize
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取我的评价失败：' . $e->getMessage());
            return Response::error('获取我的评价失败：' . $e->getMessage());
        }
    }

    /**
     * 删除评价
     */
    public function deleteReview()
    {
        try {
            $userId = $this->request->userId;
            $reviewId = $this->request->param('review_id');

            if (!$reviewId) {
                return Response::validateError('评价ID不能为空');
            }

            $review = OrderReview::where('id', $reviewId)
                ->where('user_id', $userId)
                ->find();

            if (!$review) {
                return Response::error('评价不存在');
            }

            // 开始事务
            OrderReview::startTrans();
            try {
                // 删除评价
                $review->delete();

                // 更新订单的评价状态
                $order = Order::find($review->order_id);
                if ($order) {
                    // 检查该订单是否还有其他评价
                    $remainingReviews = OrderReview::where('order_id', $review->order_id)->count();
                    if ($remainingReviews === 0) {
                        $order->is_reviewed = 0;
                        $order->save();
                    }
                }

                // 更新商品评分
                $this->updateProductRating($review->product_id);

                OrderReview::commit();
                return Response::success([], '删除成功');
            } catch (\Exception $e) {
                OrderReview::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            \think\facade\Log::error('删除评价失败：' . $e->getMessage());
            return Response::error('删除评价失败：' . $e->getMessage());
        }
    }

    /**
     * 格式化待评价订单数据
     */
    private function formatOrderForReview($order)
    {
        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_image' => $item->product_image,
                'spec_label' => $item->spec_label ?? '默认规格',
                'quantity' => $item->quantity,
                'price' => $item->price
            ];
        }

        return [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'shop_name' => $order->shop->name ?? '',
            'complete_time' => $order->complete_time,
            'items' => $items
        ];
    }

    /**
     * 格式化评价数据
     */
    private function formatReview($review)
    {
        // 确保 images 是数组格式
        $images = $review->images;
        if (is_string($images)) {
            $images = json_decode($images, true) ?? [];
        }
        if (!is_array($images)) {
            $images = [];
        }

        return [
            'id' => $review->id,
            'user_name' => $review->is_anonymous ? '匿名用户' : ($review->user->nickname ?? '用户'),
            'user_avatar' => $review->is_anonymous ? '' : ($review->user->avatar ?? ''),
            'rating' => $review->rating,
            'content' => $review->content,
            'images' => $images,
            'reply_content' => $review->reply_content,
            'reply_time' => $review->reply_time,
            'created_at' => $review->created_at
        ];
    }

    /**
     * 格式化我的评价数据
     */
    private function formatMyReview($review)
    {
        // 获取原始数据用于调试
        $rawImages = $review->getData('images');
        \think\facade\Log::info('formatMyReview - 原始images数据:', [
            'review_id' => $review->id,
            'raw_images' => $rawImages,
            'raw_type' => gettype($rawImages),
            'model_images' => $review->images,
            'model_type' => gettype($review->images)
        ]);

        // 确保 images 是数组格式
        $images = $review->images;
        if (is_string($images)) {
            $images = json_decode($images, true) ?? [];
        }
        if (!is_array($images)) {
            $images = [];
        }

        return [
            'id' => $review->id,
            'product_name' => $review->product->name ?? '',
            'product_image' => $review->product->main_image ?? '',
            'shop_name' => $review->shop->name ?? '',
            'rating' => $review->rating,
            'content' => $review->content,
            'images' => $images,
            'reply_content' => $review->reply_content,
            'reply_time' => $review->reply_time,
            'created_at' => $review->created_at
        ];
    }

    /**
     * 更新商品评分
     */
    private function updateProductRating($productId)
    {
        $avgRating = OrderReview::where('product_id', $productId)->avg('rating');
        $reviewCount = OrderReview::where('product_id', $productId)->count();

        $product = Product::find($productId);
        if ($product) {
            $product->rating = round($avgRating, 2);
            $product->review_count = $reviewCount;
            $product->save();
        }
    }
}
