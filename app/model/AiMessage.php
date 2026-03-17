<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * AI消息模型
 */
class AiMessage extends Model
{
    protected $name = 'ai_messages';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * 关联会话
     */
    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
