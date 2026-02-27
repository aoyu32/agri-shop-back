<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 订单商品模型
 */
class OrderItem extends Model
{
    protected $name = 'order_items';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    /**
     * 关联订单
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * 关联商品
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * 关联规格
     */
    public function spec()
    {
        return $this->belongsTo(ProductSpec::class, 'spec_id');
    }
}
