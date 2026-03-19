<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 社区帖子模型
 */
class CommunityPost extends Model
{
    protected $name = 'community_posts';

    // 设置json类型字段
    protected $json = ['images', 'tags'];

    // JSON字段自动转换为数组
    protected $jsonAssoc = true;

    // 设置字段类型
    protected $type = [
        'view_count' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
        'status' => 'integer',
        'is_top' => 'integer',
        'is_essence' => 'integer',
    ];

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
    public function comments()
    {
        return $this->hasMany(PostComment::class, 'post_id');
    }

    /**
     * 关联点赞
     */
    public function likes()
    {
        return $this->hasMany(PostLike::class, 'post_id');
    }

    /**
     * 获取器 - 格式化创建时间
     */
    public function getCreatedAtAttr($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }
}
