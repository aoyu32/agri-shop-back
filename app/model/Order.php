<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 订单模型
 */
class Order extends Model
{
    protected $name = 'orders';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /**
     * 关联订单商品
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联店铺
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * 生成订单号
     */
    public static function generateOrderNo(): string
    {
        return date('YmdHis') . rand(100000, 999999);
    }
}
