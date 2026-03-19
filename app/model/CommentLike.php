<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 评论点赞模型
 */
class CommentLike extends Model
{
    protected $name = 'comment_likes';

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联评论
     */
    public function comment()
    {
        return $this->belongsTo(PostComment::class, 'comment_id');
    }
}
