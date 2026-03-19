<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Order;
use app\model\Shop;
use app\model\Notification;
use app\common\Response;

/**
 * 农户订单管理控制器
 */
class MerchantOrderController extends BaseController
{
    /**
     * 获取农户订单列表
     */
    public function list()
    {
        try {
            $userId = $this->request->userId;

            // 获取农户的店铺
            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺', [], 40001);
            }

            // 检查审核状态
            if ($shop->audit_status === 0) {
                return Response::error('您的店铺正在审核中，请耐心等待', [], 40002);
            }

            if ($shop->audit_status === 2) {
                return Response::error('您的店铺审核未通过：' . $shop->audit_reason, [], 40003);
            }

            // 获取筛选参数
            $status = $this->request->param('status', '');
            $keyword = $this->request->param('keyword', '');
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 10);

            // 调试信息
            \think\facade\Log::info('查询订单 - 用户ID: ' . $userId);
            \think\facade\Log::info('查询订单 - 店铺ID: ' . $shop->id);
            \think\facade\Log::info('查询订单 - 状态筛选: ' . $status);

            // 构建查询
            $query = Order::with(['items', 'user'])
                ->where('shop_id', $shop->id)
                ->order('created_at', 'desc');

            // 状态筛选
            if ($status) {
                if ($status === 'refund') {
                    $query->where('refund_status', '<>', '');
                } elseif ($status === 'pending') {
                    // 待发货：只显示 paid 状态（已支付待发货）
                    $query->where('status', 'paid');
                } else {
                    $query->where('status', $status);
                }
            }

            // 关键词搜索
            if ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('order_no', 'like', "%{$keyword}%")
                        ->whereOr('receiver_name', 'like', "%{$keyword}%");
                });
            }

            // 先查询总数
            $total = $query->count();
            \think\facade\Log::info('查询订单 - 总数: ' . $total);

            // 分页查询
            $orders = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page
            ]);

            \think\facade\Log::info('查询订单 - 分页结果数: ' . count($orders->items()));

            // 格式化订单数据
            $list = [];
            foreach ($orders->items() as $order) {
                $list[] = $this->formatOrder($order);
            }

            return Response::success([
                'list' => $list,
                'total' => $orders->total(),
                'page' => $page,
                'page_size' => $pageSize
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取订单列表失败：' . $e->getMessage());
            \think\facade\Log::error('错误堆栈：' . $e->getTraceAsString());
            return Response::error('获取订单列表失败：' . $e->getMessage());
        }
    }


    /**
     * 获取订单详情
     */
    public function detail()
    {
        try {
            $userId = $this->request->userId;
            $orderId = $this->request->param('order_id');

            if (!$orderId) {
                return Response::validateError('订单ID不能为空');
            }

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $order = Order::where('id', $orderId)
                ->where('shop_id', $shop->id)
                ->with(['items.product', 'user'])
                ->find();

            if (!$order) {
                return Response::error('订单不存在');
            }

            return Response::success([
                'order' => $this->formatOrder($order, true)
            ]);
        } catch (\Exception $e) {
            return Response::error('获取订单详情失败：' . $e->getMessage());
        }
    }

    /**
     * 发货
     */
    public function ship()
    {
        try {
            $userId = $this->request->userId;
            $orderId = $this->request->param('order_id');
            $logistics = $this->request->param('logistics');
            $trackingNo = $this->request->param('tracking_no');

            if (!$orderId) {
                return Response::validateError('订单ID不能为空');
            }

            if (!$logistics) {
                return Response::validateError('请选择物流公司');
            }

            if (!$trackingNo) {
                return Response::validateError('请输入物流单号');
            }

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $order = Order::where('id', $orderId)
                ->where('shop_id', $shop->id)
                ->find();

            if (!$order) {
                return Response::error('订单不存在');
            }

            if (!in_array($order->status, ['pending', 'paid'])) {
                return Response::error('只有待发货订单才能发货');
            }

            $order->status = 'shipped';
            $order->logistics_company = $logistics;
            $order->tracking_no = $trackingNo;
            $order->ship_time = date('Y-m-d H:i:s');
            $order->save();

            // 通知消费者商品已发货
            $orderItems = \app\model\OrderItem::where('order_id', $orderId)->select();
            $productNames = [];
            foreach ($orderItems as $item) {
                $productNames[] = $item->product_name;
            }
            $productNamesStr = implode('、', array_slice($productNames, 0, 2));
            if (count($productNames) > 2) {
                $productNamesStr .= '等';
            }

            Notification::createNotification(
                $order->user_id,
                'order',
                '订单发货通知',
                "您的订单【{$productNamesStr}】已发货，请注意查收",
                $order->id,
                'order'
            );

            return Response::success([], '发货成功');
        } catch (\Exception $e) {
            return Response::error('发货失败：' . $e->getMessage());
        }
    }

    /**
     * 获取农户退款列表
     */
    public function refundList()
    {
        try {
            $userId = $this->request->userId;

            // 获取农户的店铺
            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺', [], 40001);
            }

            // 检查审核状态
            if ($shop->audit_status === 0) {
                return Response::error('您的店铺正在审核中，请耐心等待', [], 40002);
            }

            if ($shop->audit_status === 2) {
                return Response::error('您的店铺审核未通过：' . $shop->audit_reason, [], 40003);
            }

            // 获取筛选参数
            $status = $this->request->param('status', ''); // pending, approved, rejected, refunded
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 10);

            // 构建查询
            $query = \app\model\OrderRefund::with(['order.items', 'user'])
                ->where('shop_id', $shop->id)
                ->order('created_at', 'desc');

            // 状态筛选
            if ($status) {
                $query->where('status', $status);
            }

            // 分页查询
            $refunds = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page
            ]);

            // 格式化退款数据
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
    public function refundDetail()
    {
        try {
            $userId = $this->request->userId;
            $refundId = $this->request->param('refund_id');

            if (!$refundId) {
                return Response::validateError('退款ID不能为空');
            }

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $refund = \app\model\OrderRefund::with(['order.items', 'user'])
                ->where('id', $refundId)
                ->where('shop_id', $shop->id)
                ->find();

            if (!$refund) {
                return Response::error('退款记录不存在');
            }

            return Response::success($this->formatRefund($refund, true));
        } catch (\Exception $e) {
            return Response::error('获取退款详情失败：' . $e->getMessage());
        }
    }

    /**
     * 同意退款
     */
    public function approveRefund()
    {
        try {
            $userId = $this->request->userId;
            $refundId = $this->request->param('refund_id');

            if (!$refundId) {
                return Response::validateError('退款ID不能为空');
            }

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $refund = \app\model\OrderRefund::where('id', $refundId)
                ->where('shop_id', $shop->id)
                ->find();

            if (!$refund) {
                return Response::error('退款记录不存在');
            }

            if ($refund->status !== 'pending') {
                return Response::error('只能处理待审核的退款申请');
            }

            // 开始事务
            \app\model\OrderRefund::startTrans();
            try {
                // 更新退款状态
                $refund->status = 'approved';
                $refund->save();

                // 更新订单退款状态
                $order = Order::find($refund->order_id);
                if ($order) {
                    $order->refund_status = 'approved';
                    $order->save();
                }

                \app\model\OrderRefund::commit();

                $message = $refund->refund_type == 1 ? '已同意退款申请' : '已同意退货退款申请';
                return Response::success([], $message);
            } catch (\Exception $e) {
                \app\model\OrderRefund::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('处理退款申请失败：' . $e->getMessage());
        }
    }

    /**
     * 拒绝退款
     */
    public function rejectRefund()
    {
        try {
            $userId = $this->request->userId;
            $refundId = $this->request->param('refund_id');
            $rejectReason = $this->request->param('reject_reason');

            if (!$refundId) {
                return Response::validateError('退款ID不能为空');
            }

            if (!$rejectReason) {
                return Response::validateError('请输入拒绝原因');
            }

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $refund = \app\model\OrderRefund::where('id', $refundId)
                ->where('shop_id', $shop->id)
                ->find();

            if (!$refund) {
                return Response::error('退款记录不存在');
            }

            if ($refund->status !== 'pending') {
                return Response::error('只能处理待审核的退款申请');
            }

            // 开始事务
            \app\model\OrderRefund::startTrans();
            try {
                // 更新退款状态
                $refund->status = 'rejected';
                $refund->reject_reason = $rejectReason;
                $refund->save();

                // 更新订单退款状态
                $order = Order::find($refund->order_id);
                if ($order) {
                    $order->refund_status = 'rejected';
                    $order->save();
                }

                \app\model\OrderRefund::commit();
                return Response::success([], '已拒绝退款申请');
            } catch (\Exception $e) {
                \app\model\OrderRefund::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('处理退款申请失败：' . $e->getMessage());
        }
    }

    /**
     * 确认退款完成
     */
    public function confirmRefund()
    {
        try {
            $userId = $this->request->userId;
            $refundId = $this->request->param('refund_id');

            if (!$refundId) {
                return Response::validateError('退款ID不能为空');
            }

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $refund = \app\model\OrderRefund::where('id', $refundId)
                ->where('shop_id', $shop->id)
                ->find();

            if (!$refund) {
                return Response::error('退款记录不存在');
            }

            if ($refund->status !== 'approved') {
                return Response::error('只能确认已同意的退款');
            }

            // 开始事务
            \app\model\OrderRefund::startTrans();
            try {
                // 更新退款状态
                $refund->status = 'refunded';
                $refund->refund_time = date('Y-m-d H:i:s');
                $refund->save();

                // 更新订单退款状态
                $order = Order::find($refund->order_id);
                if ($order) {
                    $order->refund_status = 'refunded';
                    $order->save();
                }

                \app\model\OrderRefund::commit();
                return Response::success([], '退款已完成');
            } catch (\Exception $e) {
                \app\model\OrderRefund::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('确认退款失败：' . $e->getMessage());
        }
    }

    /**
     * 删除退款记录
     */
    public function deleteRefund()
    {
        try {
            $userId = $this->request->userId;
            $refundId = $this->request->param('refund_id');

            if (!$refundId) {
                return Response::validateError('退款ID不能为空');
            }

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $refund = \app\model\OrderRefund::where('id', $refundId)
                ->where('shop_id', $shop->id)
                ->find();

            if (!$refund) {
                return Response::error('退款记录不存在');
            }

            // 只能删除已拒绝、已退款、已关闭的退款记录
            if (!in_array($refund->status, ['rejected', 'refunded', 'closed'])) {
                return Response::error('只能删除已拒绝、已退款或已关闭的退款记录');
            }

            $refund->delete();
            return Response::success([], '删除成功');
        } catch (\Exception $e) {
            return Response::error('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 格式化退款数据
     */
    private function formatRefund($refund, $detail = false)
    {
        $images = $refund->refund_images;
        if (is_string($images)) {
            $images = json_decode($images, true) ?? [];
        }
        if (!is_array($images)) {
            $images = [];
        }

        $data = [
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
            'status_text' => $this->getRefundStatusText($refund->status),
            'reject_reason' => $refund->reject_reason,
            'created_at' => $refund->created_at,
            'buyer' => [
                'name' => $refund->user->nickname ?? $refund->user->username ?? '未知用户',
                'phone' => $refund->user->phone ?? '',
                'avatar' => $refund->user->avatar ?? ''
            ]
        ];

        if ($detail && $refund->order) {
            $data['order_info'] = [
                'order_no' => $refund->order->order_no,
                'total_amount' => $refund->order->total_amount,
                'actual_amount' => $refund->order->actual_amount,
                'receiver_name' => $refund->order->receiver_name,
                'receiver_phone' => $refund->order->receiver_phone,
                'receiver_address' => $refund->order->receiver_address,
                'items' => $refund->order->items->map(function ($item) {
                    return [
                        'product_name' => $item->product_name,
                        'product_image' => $item->product_image,
                        'spec_label' => $item->spec_label,
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                        'total_price' => $item->total_price
                    ];
                })
            ];
        }

        return $data;
    }

    /**
     * 删除订单
     */
    public function delete()
    {
        try {
            $userId = $this->request->userId;
            $orderId = $this->request->param('order_id');

            if (!$orderId) {
                return Response::validateError('订单ID不能为空');
            }

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $order = Order::where('id', $orderId)
                ->where('shop_id', $shop->id)
                ->find();

            if (!$order) {
                return Response::error('订单不存在');
            }

            if (!in_array($order->status, ['completed', 'cancelled'])) {
                return Response::error('只有已完成或已取消的订单才能删除');
            }

            $order->delete();

            return Response::success([], '删除成功');
        } catch (\Exception $e) {
            return Response::error('删除订单失败：' . $e->getMessage());
        }
    }

    /**
     * 获取订单统计
     */
    public function statistics()
    {
        try {
            $userId = $this->request->userId;

            $shop = Shop::where('user_id', $userId)->find();
            if (!$shop) {
                return Response::error('您还没有开通店铺');
            }

            $pendingCount = Order::where('shop_id', $shop->id)->where('status', 'pending')->count();
            $shippedCount = Order::where('shop_id', $shop->id)->where('status', 'shipped')->count();
            $completedCount = Order::where('shop_id', $shop->id)->where('status', 'completed')->count();
            $refundCount = Order::where('shop_id', $shop->id)->where('refund_status', '<>', '')->count();
            $totalCount = Order::where('shop_id', $shop->id)->count();

            return Response::success([
                'statistics' => [
                    'pending_count' => $pendingCount,
                    'shipped_count' => $shippedCount,
                    'completed_count' => $completedCount,
                    'refund_count' => $refundCount,
                    'total_count' => $totalCount
                ]
            ]);
        } catch (\Exception $e) {
            return Response::error('获取订单统计失败：' . $e->getMessage());
        }
    }

    /**
     * 格式化订单数据
     */
    private function formatOrder($order, $detail = false)
    {
        $data = [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'buyer' => [
                'name' => $order->receiver_name,
                'phone' => $order->receiver_phone,
                'avatar' => $order->user->avatar ?? ''
            ],
            'products' => [],
            'total_amount' => $order->actual_amount,
            'status' => $order->status,
            'create_time' => $order->created_at,
            'address' => $order->receiver_address,
            'logistics_company' => $order->logistics_company,
            'tracking_no' => $order->tracking_no
        ];

        foreach ($order->items as $item) {
            $data['products'][] = [
                'id' => $item->id,
                'name' => $item->product_name,
                'spec' => $item->spec_label ?? '默认规格',
                'quantity' => $item->quantity,
                'price' => $item->price,
                'image' => $item->product_image
            ];
        }

        if ($order->refund_status) {
            $data['refund_type'] = $order->refund_type;
            $data['refund_reason'] = $order->refund_reason;
            $data['refund_description'] = $order->refund_description;
            $data['refund_status'] = $this->getRefundStatusText($order->refund_status);
            $data['refund_time'] = $order->refund_requested_at;

            if ($order->refund_images) {
                $images = json_decode($order->refund_images, true);
                $data['refund_images'] = array_map(function ($img) {
                    return ['url' => $img];
                }, $images);
            } else {
                $data['refund_images'] = [];
            }
        }

        if ($detail) {
            $data['shipping_fee'] = $order->shipping_fee;
            $data['logistics_company'] = $order->logistics_company;
            $data['tracking_no'] = $order->tracking_no;
            $data['ship_time'] = $order->ship_time;
            $data['complete_time'] = $order->complete_time;
        }

        return $data;
    }

    /**
     * 获取退款状态文本
     */
    private function getRefundStatusText($status)
    {
        $statusMap = [
            'pending' => '待审核',
            'approved' => '已同意',
            'rejected' => '已拒绝',
            'refunded' => '已退款',
            'closed' => '已关闭'
        ];

        return $statusMap[$status] ?? '未知状态';
    }
}
