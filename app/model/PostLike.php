<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 帖子点赞模型
 */
class PostLike extends Model
{
    protected $name = 'post_likes';

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联帖子
     */
    public function post()
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }
}
