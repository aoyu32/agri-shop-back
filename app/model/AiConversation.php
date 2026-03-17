<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * AI会话模型
 */
class AiConversation extends Model
{
    protected $name = 'ai_conversations';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联消息
     */
    public function messages()
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }

    /**
     * 获取最后一条消息
     */
    public function getLastMessageAttr($value, $data)
    {
        $lastMessage = AiMessage::where('conversation_id', $data['id'])
            ->order('created_at', 'desc')
            ->find();

        return $lastMessage ? $lastMessage->content : '';
    }
}
