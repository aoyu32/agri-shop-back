<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Order;
use app\model\OrderItem;
use app\model\Product;
use app\model\ProductSpec;
use app\model\Cart;
use app\common\Response;
use think\facade\Db;

/**
 * 订单控制器
 */
class OrderController extends BaseController
{
    /**
     * 创建订单
     */
    public function create()
    {
        try {
            $userId = $this->request->userId;
            $type = $this->request->param('type', 'cart'); // 订单类型：cart 或 buy_now
            $addressId = $this->request->param('address_id'); // 收货地址ID
            $receiverName = $this->request->param('receiver_name');
            $receiverPhone = $this->request->param('receiver_phone');
            $receiverAddress = $this->request->param('receiver_address');
            $remark = $this->request->param('remark', '');

            // 优先使用地址ID，如果没有则使用手动填写的地址
            if ($addressId) {
                $address = \app\model\UserAddress::where('id', $addressId)
                    ->where('user_id', $userId)
                    ->find();

                if (!$address) {
                    return Response::validateError('收货地址不存在');
                }

                $receiverName = $address->receiver_name;
                $receiverPhone = $address->receiver_phone;
                $receiverAddress = $address->full_address;
            } else {
                // 如果没有地址ID，验证手动填写的地址信息
                if (!$receiverName || !$receiverPhone || !$receiverAddress) {
                    return Response::validateError('请填写完整的收货信息');
                }
            }

            // 开启事务
            Db::startTrans();

            try {
                $orderList = [];

                if ($type === 'buy_now') {
                    // 立即购买模式
                    $productId = (int)$this->request->param('product_id');
                    $specId = $this->request->param('spec_id') ? (int)$this->request->param('spec_id') : null;
                    $quantity = (int)$this->request->param('quantity', 1);

                    if (!$productId || $quantity <= 0) {
                        throw new \Exception('商品信息不完整');
                    }

                    // 获取商品信息
                    $product = Product::with(['shop'])->find($productId);
                    if (!$product) {
                        throw new \Exception('商品不存在');
                    }

                    // 检查商品状态
                    if ($product->status !== 'on_sale') {
                        throw new \Exception("商品【{$product->name}】已下架");
                    }

                    // 获取规格信息
                    $spec = null;
                    if ($specId) {
                        $spec = ProductSpec::where('id', $specId)
                            ->where('product_id', $productId)
                            ->find();
                        if (!$spec) {
                            throw new \Exception('商品规格不存在');
                        }
                    }

                    // 计算价格
                    $price = (float)$product->price;
                    if ($spec) {
                        $price += (float)$spec->price_diff;
                    }

                    // 检查库存
                    $stock = $spec ? $spec->stock : $product->stock;
                    if ($stock < $quantity) {
                        throw new \Exception("商品【{$product->name}】库存不足");
                    }

                    $totalAmount = $price * $quantity;

                    // 创建订单
                    $order = Order::create([
                        'order_no' => Order::generateOrderNo(),
                        'user_id' => $userId,
                        'shop_id' => $product->shop_id,
                        'address_id' => $addressId ?? null,
                        'total_amount' => $totalAmount,
                        'shipping_fee' => 0,
                        'actual_amount' => $totalAmount,
                        'receiver_name' => $receiverName,
                        'receiver_phone' => $receiverPhone,
                        'receiver_address' => $receiverAddress,
                        'status' => 'pending',
                        'remark' => $remark
                    ]);

                    // 创建订单商品
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_image' => $product->main_image,
                        'spec_id' => $spec ? $spec->id : null,
                        'spec_label' => $spec ? $spec->spec_label : null,
                        'price' => $price,
                        'quantity' => $quantity,
                        'total_price' => $totalAmount
                    ]);

                    // 扣减库存
                    if ($specId) {
                        ProductSpec::where('id', $specId)
                            ->dec('stock', $quantity)
                            ->update();
                    } else {
                        Product::where('id', $productId)
                            ->dec('stock', $quantity)
                            ->update();
                    }

                    $orderList[] = [
                        'order_id' => $order->id,
                        'order_no' => $order->order_no,
                        'total_amount' => $order->actual_amount
                    ];
                } else {
                    // 购物车模式
                    $cartIds = $this->request->param('cart_ids');

                    if (empty($cartIds)) {
                        throw new \Exception('请选择要购买的商品');
                    }

                    // 获取购物车商品
                    $cartItems = Cart::where('user_id', $userId)
                        ->whereIn('id', $cartIds)
                        ->with(['product', 'spec'])
                        ->select();

                    if ($cartItems->isEmpty()) {
                        throw new \Exception('购物车商品不存在');
                    }

                    // 按店铺分组创建订单
                    $shopOrders = [];
                    foreach ($cartItems as $item) {
                        $shopId = $item->product->shop_id;
                        if (!isset($shopOrders[$shopId])) {
                            $shopOrders[$shopId] = [];
                        }
                        $shopOrders[$shopId][] = $item;
                    }

                    // 为每个店铺创建订单
                    foreach ($shopOrders as $shopId => $items) {
                        $totalAmount = 0;
                        $orderItems = [];

                        // 计算订单金额并准备订单商品数据
                        foreach ($items as $item) {
                            $product = $item->product;
                            $spec = $item->spec;

                            // 检查商品状态
                            if ($product->status !== 'on_sale') {
                                throw new \Exception("商品【{$product->name}】已下架");
                            }

                            // 计算价格
                            $price = (float)$product->price;
                            if ($spec) {
                                $price += (float)$spec->price_diff;
                            }

                            // 检查库存
                            $stock = $spec ? $spec->stock : $product->stock;
                            if ($stock < $item->quantity) {
                                throw new \Exception("商品【{$product->name}】库存不足");
                            }

                            $itemTotal = $price * $item->quantity;
                            $totalAmount += $itemTotal;

                            $orderItems[] = [
                                'product_id' => $product->id,
                                'product_name' => $product->name,
                                'product_image' => $product->main_image,
                                'spec_id' => $spec ? $spec->id : null,
                                'spec_label' => $spec ? $spec->spec_label : null,
                                'price' => $price,
                                'quantity' => $item->quantity,
                                'total_price' => $itemTotal
                            ];
                        }

                        // 创建订单
                        $order = Order::create([
                            'order_no' => Order::generateOrderNo(),
                            'user_id' => $userId,
                            'shop_id' => $shopId,
                            'address_id' => $addressId ?? null,
                            'total_amount' => $totalAmount,
                            'shipping_fee' => 0,
                            'actual_amount' => $totalAmount,
                            'receiver_name' => $receiverName,
                            'receiver_phone' => $receiverPhone,
                            'receiver_address' => $receiverAddress,
                            'status' => 'pending',
                            'remark' => $remark
                        ]);

                        // 创建订单商品
                        foreach ($orderItems as &$orderItem) {
                            $orderItem['order_id'] = $order->id;
                        }
                        (new OrderItem())->saveAll($orderItems);

                        // 扣减库存
                        foreach ($items as $item) {
                            if ($item->spec_id) {
                                ProductSpec::where('id', $item->spec_id)
                                    ->dec('stock', $item->quantity)
                                    ->update();
                            } else {
                                Product::where('id', $item->product_id)
                                    ->dec('stock', $item->quantity)
                                    ->update();
                            }
                        }

                        $orderList[] = [
                            'order_id' => $order->id,
                            'order_no' => $order->order_no,
                            'total_amount' => $order->actual_amount
                        ];
                    }

                    // 删除购物车商品
                    Cart::where('user_id', $userId)
                        ->whereIn('id', $cartIds)
                        ->delete();
                }

                Db::commit();

                return Response::success([
                    'orders' => $orderList
                ], '订单创建成功');
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('创建订单失败：' . $e->getMessage());
        }
    }

    /**
     * 获取订单列表
     */
    public function list()
    {
        try {
            $userId = $this->request->userId;
            $status = $this->request->param('status', ''); // all, pending, paid, shipped, completed, refund
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 10);

            $query = Order::where('user_id', $userId)
                ->with(['items', 'shop']);

            // 状态筛选
            if ($status && $status !== 'all') {
                if ($status === 'refund') {
                    // 退款订单：查询有退款申请的订单
                    $query->whereNotNull('refund_status');
                } else {
                    $query->where('status', $status);
                }
            }

            $total = $query->count();
            $list = $query->order('created_at', 'desc')
                ->page($page, $limit)
                ->select();

            // 如果是退款列表，需要关联退款记录
            $refundMap = [];
            if ($status === 'refund') {
                $orderIds = $list->column('id');
                if (!empty($orderIds)) {
                    $refunds = \app\model\OrderRefund::whereIn('order_id', $orderIds)
                        ->where('user_id', $userId)
                        ->select();
                    foreach ($refunds as $refund) {
                        $refundMap[$refund->order_id] = $refund->id;
                    }
                }
            }

            // 格式化数据
            $orders = [];
            foreach ($list as $order) {
                $orderData = [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'shop_id' => $order->shop_id,
                    'shop_name' => $order->shop ? $order->shop->shop_name : '未知店铺',
                    'total_amount' => (float)$order->total_amount,
                    'shipping_fee' => (float)$order->shipping_fee,
                    'actual_amount' => (float)$order->actual_amount,
                    'status' => $order->status,
                    'status_text' => $this->getStatusText($order->status),
                    'is_reviewed' => $order->is_reviewed,
                    'refund_status' => $order->refund_status,
                    'receiver_name' => $order->receiver_name,
                    'receiver_phone' => $order->receiver_phone,
                    'receiver_address' => $order->receiver_address,
                    'remark' => $order->remark,
                    'payment_time' => $order->payment_time,
                    'ship_time' => $order->ship_time,
                    'complete_time' => $order->complete_time,
                    'created_at' => $order->created_at,
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'product_image' => $item->product_image,
                            'spec_label' => $item->spec_label,
                            'price' => (float)$item->price,
                            'quantity' => $item->quantity,
                            'total_price' => (float)$item->total_price
                        ];
                    })
                ];

                // 如果是退款列表，添加退款ID
                if ($status === 'refund' && isset($refundMap[$order->id])) {
                    $orderData['refund_id'] = $refundMap[$order->id];
                }

                $orders[] = $orderData;
            }

            return Response::success([
                'list' => $orders,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]);
        } catch (\Exception $e) {
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
            $orderId = (int)$this->request->param('id');

            if (!$orderId) {
                return Response::validateError('订单ID不能为空');
            }

            $order = Order::where('id', $orderId)
                ->where('user_id', $userId)
                ->with(['items', 'shop'])
                ->find();

            if (!$order) {
                return Response::error('订单不存在');
            }

            $data = [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'shop_id' => $order->shop_id,
                'shop_name' => $order->shop ? $order->shop->shop_name : '未知店铺',
                'total_amount' => (float)$order->total_amount,
                'shipping_fee' => (float)$order->shipping_fee,
                'actual_amount' => (float)$order->actual_amount,
                'status' => $order->status,
                'status_text' => $this->getStatusText($order->status),
                'receiver_name' => $order->receiver_name,
                'receiver_phone' => $order->receiver_phone,
                'receiver_address' => $order->receiver_address,
                'remark' => $order->remark,
                'payment_method' => $order->payment_method,
                'payment_time' => $order->payment_time,
                'ship_time' => $order->ship_time,
                'complete_time' => $order->complete_time,
                'cancel_time' => $order->cancel_time,
                'cancel_reason' => $order->cancel_reason,
                'created_at' => $order->created_at,
                'items' => $order->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'product_image' => $item->product_image,
                        'spec_label' => $item->spec_label,
                        'price' => (float)$item->price,
                        'quantity' => $item->quantity,
                        'total_price' => (float)$item->total_price
                    ];
                })
            ];

            return Response::success(['order' => $data]);
        } catch (\Exception $e) {
            return Response::error('获取订单详情失败：' . $e->getMessage());
        }
    }

    /**
     * 取消订单
     */
    public function cancel()
    {
        try {
            $userId = $this->request->userId;
            $orderId = (int)$this->request->param('id');
            $reason = $this->request->param('reason', '');

            if (!$orderId) {
                return Response::validateError('订单ID不能为空');
            }

            $order = Order::where('id', $orderId)
                ->where('user_id', $userId)
                ->find();

            if (!$order) {
                return Response::error('订单不存在');
            }

            // 只有待付款和待发货状态可以取消
            if (!in_array($order->status, ['pending', 'paid'])) {
                return Response::error('当前订单状态不允许取消');
            }

            Db::startTrans();
            try {
                // 更新订单状态
                $order->status = 'cancelled';
                $order->cancel_time = date('Y-m-d H:i:s');
                $order->cancel_reason = $reason;
                $order->save();

                // 恢复库存
                $items = OrderItem::where('order_id', $orderId)->select();
                foreach ($items as $item) {
                    if ($item->spec_id) {
                        ProductSpec::where('id', $item->spec_id)
                            ->inc('stock', $item->quantity)
                            ->update();
                    } else {
                        Product::where('id', $item->product_id)
                            ->inc('stock', $item->quantity)
                            ->update();
                    }
                }

                Db::commit();
                return Response::success([], '订单已取消');
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('取消订单失败：' . $e->getMessage());
        }
    }

    /**
     * 确认收货
     */
    public function confirm()
    {
        try {
            $userId = $this->request->userId;
            $orderId = (int)$this->request->param('id');

            if (!$orderId) {
                return Response::validateError('订单ID不能为空');
            }

            $order = Order::where('id', $orderId)
                ->where('user_id', $userId)
                ->find();

            if (!$order) {
                return Response::error('订单不存在');
            }

            // 只有已发货状态可以确认收货
            if ($order->status !== 'shipped') {
                return Response::error('当前订单状态不允许确认收货');
            }

            // 开始事务
            Order::startTrans();
            try {
                // 更新订单状态
                $order->status = 'completed';
                $order->complete_time = date('Y-m-d H:i:s');
                $order->save();

                // 增加商品销量
                $orderItems = \app\model\OrderItem::where('order_id', $orderId)->select();
                foreach ($orderItems as $item) {
                    \app\model\Product::where('id', $item->product_id)
                        ->inc('sales', $item->quantity)
                        ->update();
                }

                Order::commit();
                return Response::success([], '确认收货成功');
            } catch (\Exception $e) {
                Order::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('确认收货失败：' . $e->getMessage());
        }
    }

    /**
     * 支付订单
     */
    public function pay()
    {
        try {
            $userId = $this->request->userId;
            $orderId = (int)$this->request->param('id');
            $paymentMethod = $this->request->param('payment_method', 'alipay');

            if (!$orderId) {
                return Response::validateError('订单ID不能为空');
            }

            $order = Order::where('id', $orderId)
                ->where('user_id', $userId)
                ->find();

            if (!$order) {
                return Response::error('订单不存在');
            }

            // 只有待付款状态可以支付
            if ($order->status !== 'pending') {
                return Response::error('当前订单状态不允许支付');
            }

            // 更新订单状态为已付款
            $order->status = 'paid';
            $order->payment_method = $paymentMethod;
            $order->payment_time = date('Y-m-d H:i:s');
            $order->save();

            return Response::success([
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'status' => $order->status
            ], '支付成功');
        } catch (\Exception $e) {
            return Response::error('支付失败：' . $e->getMessage());
        }
    }

    /**
     * 删除订单
     */
    public function delete()
    {
        try {
            $userId = $this->request->userId;
            $orderId = (int)$this->request->param('id');

            if (!$orderId) {
                return Response::validateError('订单ID不能为空');
            }

            $order = Order::where('id', $orderId)
                ->where('user_id', $userId)
                ->find();

            if (!$order) {
                return Response::error('订单不存在');
            }

            // 只有已完成、已取消的订单可以删除
            if (!in_array($order->status, ['completed', 'cancelled'])) {
                return Response::error('当前订单状态不允许删除');
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

            $stats = [
                'pending' => Order::where('user_id', $userId)->where('status', 'pending')->count(),
                'paid' => Order::where('user_id', $userId)->where('status', 'paid')->count(),
                'shipped' => Order::where('user_id', $userId)->where('status', 'shipped')->count(),
                'completed' => Order::where('user_id', $userId)->where('status', 'completed')->count(),
                'refund' => Order::where('user_id', $userId)->where('status', 'refund')->count()
            ];

            return Response::success(['statistics' => $stats]);
        } catch (\Exception $e) {
            return Response::error('获取统计失败：' . $e->getMessage());
        }
    }

    /**
     * 获取状态文本
     */
    private function getStatusText($status): string
    {
        $statusMap = [
            'pending' => '待付款',
            'paid' => '待发货',
            'shipped' => '待收货',
            'completed' => '已完成',
            'cancelled' => '已取消',
            'refund' => '退款中'
        ];

        return $statusMap[$status] ?? '未知状态';
    }
}
