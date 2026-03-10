<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Order;
use app\model\Shop;
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

            return Response::success([], '发货成功');
        } catch (\Exception $e) {
            return Response::error('发货失败：' . $e->getMessage());
        }
    }

    /**
     * 同意退款
     */
    public function approveRefund()
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

            if (empty($order->refund_status)) {
                return Response::error('该订单没有退款申请');
            }

            if ($order->refund_type === 'refund') {
                $order->refund_status = 'approved';
                $order->refund_approved_at = date('Y-m-d H:i:s');
                $message = '已同意退款申请，系统将自动退款给买家';
            } else {
                $order->refund_status = 'approved_waiting_return';
                $order->refund_approved_at = date('Y-m-d H:i:s');
                $message = '已同意退货申请，请等待买家退货';
            }

            $order->save();

            return Response::success([], $message);
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
            $orderId = $this->request->param('order_id');
            $rejectReason = $this->request->param('reject_reason');

            if (!$orderId) {
                return Response::validateError('订单ID不能为空');
            }

            if (!$rejectReason) {
                return Response::validateError('请输入拒绝原因');
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

            if (empty($order->refund_status)) {
                return Response::error('该订单没有退款申请');
            }

            $order->refund_status = 'rejected';
            $order->refund_reject_reason = $rejectReason;
            $order->refund_rejected_at = date('Y-m-d H:i:s');
            $order->save();

            return Response::success([], '已拒绝退款申请');
        } catch (\Exception $e) {
            return Response::error('处理退款申请失败：' . $e->getMessage());
        }
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
            'pending' => '待商家处理',
            'approved' => '商家已同意，退款处理中',
            'approved_waiting_return' => '商家已同意，等待买家退货',
            'rejected' => '商家已拒绝',
            'completed' => '退款已完成'
        ];

        return $statusMap[$status] ?? '未知状态';
    }
}
