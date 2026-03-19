<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 帖子评论模型
 */
class PostComment extends Model
{
    protected $name = 'post_comments';

    // 设置字段类型
    protected $type = [
        'like_count' => 'integer',
        'status' => 'integer',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联回复的用户
     */
    public function replyToUser()
    {
        return $this->belongsTo(User::class, 'reply_to_user_id');
    }

    /**
     * 关联帖子
     */
    public function post()
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }

    /**
     * 关联父评论
     */
    public function parent()
    {
        return $this->belongsTo(PostComment::class, 'parent_id');
    }

    /**
     * 关联子评论（回复）
     */
    public function replies()
    {
        return $this->hasMany(PostComment::class, 'parent_id');
    }

    /**
     * 关联点赞
     */
    public function likes()
    {
        return $this->hasMany(CommentLike::class, 'comment_id');
    }

    /**
     * 获取器 - 格式化创建时间
     */
    public function getCreatedAtAttr($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }
}
