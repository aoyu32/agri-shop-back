<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Order;
use app\model\OrderRefund;
use app\common\Response;

/**
 * 退款控制器
 */
class RefundController extends BaseController
{
    /**
     * 申请退款
     */
    public function apply()
    {
        try {
            $userId = $this->request->userId;
            $orderId = $this->request->param('order_id');
            $refundType = $this->request->param('refund_type', 1); // 1-仅退款, 2-退货退款
            $refundReason = $this->request->param('refund_reason');
            $refundDescription = $this->request->param('refund_description', '');
            $refundImages = $this->request->param('refund_images', []);

            // 验证必填字段
            if (!$orderId) {
                return Response::validateError('订单ID不能为空');
            }
            if (!$refundReason) {
                return Response::validateError('退款原因不能为空');
            }

            // 查询订单
            $order = Order::where('id', $orderId)
                ->where('user_id', $userId)
                ->find();

            if (!$order) {
                return Response::error('订单不存在');
            }

            // 验证订单状态
            if (!in_array($order->status, ['paid', 'shipped', 'completed'])) {
                return Response::error('当前订单状态不支持退款');
            }

            // 检查是否已经申请过退款
            if ($order->refund_status && in_array($order->refund_status, ['applying', 'approved'])) {
                return Response::error('该订单已申请退款，请勿重复申请');
            }

            // 生成退款单号
            $refundNo = 'RF' . date('YmdHis') . rand(1000, 9999);

            // 开始事务
            Order::startTrans();
            try {
                // 创建退款记录
                OrderRefund::create([
                    'refund_no' => $refundNo,
                    'order_id' => $orderId,
                    'order_no' => $order->order_no,
                    'user_id' => $userId,
                    'shop_id' => $order->shop_id,
                    'refund_type' => $refundType,
                    'refund_reason' => $refundReason,
                    'refund_amount' => $order->total_amount,
                    'refund_description' => $refundDescription,
                    'refund_images' => $refundImages,
                    'status' => 'pending'
                ]);

                // 更新订单退款状态
                $order->refund_status = 'applying';
                $order->save();

                Order::commit();
                return Response::success([
                    'refund_no' => $refundNo
                ], '退款申请提交成功');
            } catch (\Exception $e) {
                Order::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            \think\facade\Log::error('申请退款失败：' . $e->getMessage());
            return Response::error('申请退款失败：' . $e->getMessage());
        }
    }

    /**
     * 获取我的退款列表
     */
    public function myList()
    {
        try {
            $userId = $this->request->userId;
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 10);
            $status = $this->request->param('status', '');

            $query = OrderRefund::with(['order'])
                ->where('user_id', $userId);

            // 状态筛选
            if ($status) {
                $query->where('status', $status);
            }

            $query->order('created_at', 'desc');

            $refunds = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page
            ]);

            $list = [];
            foreach ($refunds->items() as $refund) {
                $list[] = $this->formatRefund($refund);
            }

            return Response::success([
                'list' => $list,
                'total' => $refunds->total(),
                'page' => $page,
                'page_size' => $pageSize
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取退款列表失败：' . $e->getMessage());
            return Response::error('获取退款列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取退款详情
     */
    public function detail()
    {
        try {
            $userId = $this->request->userId;
            $refundId = $this->request->param('refund_id');

            if (!$refundId) {
                return Response::validateError('退款ID不能为空');
            }

            $refund = OrderRefund::with(['order'])
                ->where('id', $refundId)
                ->where('user_id', $userId)
                ->find();

            if (!$refund) {
                return Response::error('退款记录不存在');
            }

            return Response::success($this->formatRefund($refund));
        } catch (\Exception $e) {
            \think\facade\Log::error('获取退款详情失败：' . $e->getMessage());
            return Response::error('获取退款详情失败：' . $e->getMessage());
        }
    }

    /**
     * 取消退款申请
     */
    public function cancel()
    {
        try {
            $userId = $this->request->userId;
            $refundId = $this->request->param('refund_id');

            if (!$refundId) {
                return Response::validateError('退款ID不能为空');
            }

            $refund = OrderRefund::where('id', $refundId)
                ->where('user_id', $userId)
                ->find();

            if (!$refund) {
                return Response::error('退款记录不存在');
            }

            if ($refund->status !== 'pending') {
                return Response::error('当前状态不支持取消');
            }

            // 开始事务
            OrderRefund::startTrans();
            try {
                // 更新退款状态
                $refund->status = 'closed';
                $refund->save();

                // 更新订单退款状态
                $order = Order::find($refund->order_id);
                if ($order) {
                    $order->refund_status = null;
                    $order->save();
                }

                OrderRefund::commit();
                return Response::success([], '已取消退款申请');
            } catch (\Exception $e) {
                OrderRefund::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            \think\facade\Log::error('取消退款失败：' . $e->getMessage());
            return Response::error('取消退款失败：' . $e->getMessage());
        }
    }

    /**
     * 格式化退款数据
     */
    private function formatRefund($refund)
    {
        $images = $refund->refund_images;
        if (is_string($images)) {
            $images = json_decode($images, true) ?? [];
        }
        if (!is_array($images)) {
            $images = [];
        }

        return [
            'id' => $refund->id,
            'refund_no' => $refund->refund_no,
            'order_id' => $refund->order_id,
            'order_no' => $refund->order_no,
            'refund_type' => $refund->refund_type,
            'refund_type_text' => $refund->refund_type == 1 ? '仅退款' : '退货退款',
            'refund_reason' => $refund->refund_reason,
            'refund_amount' => $refund->refund_amount,
            'refund_description' => $refund->refund_description,
            'refund_images' => $images,
            'status' => $refund->status,
            'status_text' => $this->getStatusText($refund->status),
            'reject_reason' => $refund->reject_reason,
            'logistics_company' => $refund->logistics_company,
            'tracking_no' => $refund->tracking_no,
            'refund_time' => $refund->refund_time,
            'created_at' => $refund->created_at,
            'updated_at' => $refund->updated_at
        ];
    }

    /**
     * 获取状态文本
     */
    private function getStatusText($status)
    {
        $statusMap = [
            'pending' => '待审核',
            'approved' => '已同意',
            'rejected' => '已拒绝',
            'refunded' => '已退款',
            'closed' => '已关闭'
        ];
        return $statusMap[$status] ?? '未知';
    }
}
