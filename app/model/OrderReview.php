<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 订单评价模型
 */
class OrderReview extends Model
{
    protected $name = 'order_reviews';

    // 设置字段信息
    protected $schema = [
        'id'             => 'int',
        'order_id'       => 'int',
        'user_id'        => 'int',
        'shop_id'        => 'int',
        'product_id'     => 'int',
        'order_item_id'  => 'int',
        'rating'         => 'int',
        'content'        => 'string',
        'images'         => 'string',
        'is_anonymous'   => 'int',
        'likes_count'    => 'int',
        'reply_content'  => 'string',
        'reply_time'     => 'string',
        'created_at'     => 'string',
        'updated_at'     => 'string',
    ];

    /**
     * images 获取器 - 确保返回数组
     */
    public function getImagesAttr($value)
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
     * images 修改器 - 确保存储为JSON
     */
    public function setImagesAttr($value)
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $value;
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 关联商品
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // 关联店铺
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    // 关联订单
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
