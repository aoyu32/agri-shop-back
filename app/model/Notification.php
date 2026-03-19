<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 消息通知模型
 */
class Notification extends Model
{
    protected $name = 'notifications';

    // 设置字段信息
    protected $schema = [
        'id' => 'bigint',
        'user_id' => 'bigint',
        'type' => 'string',
        'title' => 'string',
        'content' => 'string',
        'related_id' => 'bigint',
        'related_type' => 'string',
        'is_read' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 创建通知
     */
    public static function createNotification($userId, $type, $title, $content, $relatedId = null, $relatedType = null)
    {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
            'is_read' => 0
        ]);
    }

    /**
     * 标记为已读
     */
    public function markAsRead()
    {
        $this->is_read = 1;
        return $this->save();
    }

    /**
     * 批量标记为已读
     */
    public static function markAllAsRead($userId)
    {
        return self::where('user_id', $userId)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
    }

    /**
     * 获取未读数量
     */
    public static function getUnreadCount($userId)
    {
        return self::where('user_id', $userId)
            ->where('is_read', 0)
            ->count();
    }
}
