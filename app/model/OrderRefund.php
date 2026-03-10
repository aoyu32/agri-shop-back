<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 订单退款模型
 */
class OrderRefund extends Model
{
    protected $name = 'order_refunds';

    // 设置字段信息
    protected $schema = [
        'id'                  => 'int',
        'refund_no'           => 'string',
        'order_id'            => 'int',
        'order_no'            => 'string',
        'user_id'             => 'int',
        'shop_id'             => 'int',
        'refund_type'         => 'int',
        'refund_reason'       => 'string',
        'refund_amount'       => 'float',
        'refund_description'  => 'string',
        'refund_images'       => 'string',
        'status'              => 'string',
        'reject_reason'       => 'string',
        'logistics_company'   => 'string',
        'tracking_no'         => 'string',
        'refund_time'         => 'string',
        'created_at'          => 'string',
        'updated_at'          => 'string',
    ];

    /**
     * refund_images 获取器 - 确保返回数组
     */
    public function getRefundImagesAttr($value)
    {
        if (empty($value)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * refund_images 修改器 - 确保存储为JSON
     */
    public function setRefundImagesAttr($value)
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $value;
    }

    // 关联订单
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 关联店铺
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
}
