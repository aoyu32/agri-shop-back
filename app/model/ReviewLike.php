<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 评价点赞记录模型
 */
class ReviewLike extends Model
{
    protected $name = 'review_likes';

    // 设置字段信息
    protected $schema = [
        'id'         => 'int',
        'review_id'  => 'int',
        'user_id'    => 'int',
        'created_at' => 'string',
    ];

    // 关联评价
    public function review()
    {
        return $this->belongsTo(OrderReview::class, 'review_id');
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
