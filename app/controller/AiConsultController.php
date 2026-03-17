<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\AiConversation;
use app\model\AiMessage;
use app\service\CozeService;
use app\common\Response;
use think\facade\Db;

/**
 * AI咨询控制器
 */
class AiConsultController extends BaseController
{
    /**
     * 发送消息（流式输出）
     */
    public function sendMessageStream()
    {
        try {
            // 禁用所有输出缓冲
            while (ob_get_level()) {
                ob_end_clean();
            }

            // 设置无限执行时间
            set_time_limit(0);

            // 关闭 Apache 的输出压缩
            if (function_exists('apache_setenv')) {
                apache_setenv('no-gzip', '1');
            }

            // 禁用 zlib 输出压缩
            ini_set('zlib.output_compression', '0');

            // 禁用隐式刷新
            ini_set('implicit_flush', '1');

            $userId = $this->request->userId;
            $conversationId = $this->request->param('conversation_id');
            $content = $this->request->param('content', '');
            $images = $this->request->param('images', []);  // 图片URL数组

            if (empty($content) && empty($images)) {
                // 设置响应头
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no');
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization');

                echo "data: " . json_encode(['error' => '消息内容和图片不能同时为空'], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                return;
            }

            // 设置响应头为流式输出
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');

            // 查询会话
            $conversation = null;
            $cozeConversationId = null;

            if ($conversationId) {
                $conversation = AiConversation::where('id', $conversationId)
                    ->where('user_id', $userId)
                    ->find();

                if (!$conversation) {
                    echo "data: " . json_encode(['error' => '会话不存在']) . "\n\n";
                    return;
                }

                $cozeConversationId = $conversation->conversation_id;
            }

            // 如果没有会话，创建新会话
            if (!$conversation) {
                $conversation = AiConversation::create([
                    'user_id' => $userId,
                    'title' => mb_substr($content, 0, 20) . '...',
                    'message_count' => 0,
                    'status' => 1
                ]);
            }

            // 构建消息内容（支持图文混合）
            $messageContent = $content;
            $messageContentType = 'text';

            if (!empty($images)) {
                // 如果有图片，使用 JSON 格式存储图文混合消息
                $messageContent = json_encode([
                    'text' => $content,
                    'images' => $images
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $messageContentType = 'mixed';
            }

            // 保存用户消息
            $userMessage = AiMessage::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'role' => 'user',
                'content' => $messageContent,
                'content_type' => $messageContentType
            ]);

            // 发送初始信息
            echo "data: " . json_encode([
                'type' => 'start',
                'conversation_id' => $conversation->id,
                'user_message' => [
                    'id' => $userMessage->id,
                    'content' => $content
                ]
            ], JSON_UNESCAPED_UNICODE) . "\n\n";

            // 发送一些填充数据以触发浏览器开始接收流
            // 这可以帮助绕过某些代理或服务器的缓冲
            echo str_repeat(' ', 2048) . "\n";
            flush();

            // 调用Coze Stream API并实时输出
            $this->streamCozeResponse($content, $cozeConversationId, $conversation, $userId, $images);
        } catch (\Exception $e) {
            echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
            flush();
        }
    }

    /**
     * 流式调用Coze API并输出
     */
    private function streamCozeResponse($content, $cozeConversationId, $conversation, $userId, $images = [])
    {
        try {
            $cozeService = new CozeService();

            // 获取历史消息（最近10轮对话，即20条消息）
            $historyMessages = [];
            if ($conversation->id) {
                $messages = AiMessage::where('conversation_id', $conversation->id)
                    ->order('created_at', 'desc')
                    ->limit(20)
                    ->select();

                // 反转顺序，使其按时间正序排列
                $messages = array_reverse($messages->toArray());

                // 构建历史消息数组
                foreach ($messages as $msg) {
                    $msgContent = $msg['content'];
                    $msgContentType = 'text';
                    $msgImages = [];

                    // 解析图文混合消息
                    if ($msg['content_type'] === 'mixed') {
                        $parsed = json_decode($msg['content'], true);
                        if ($parsed) {
                            $msgContent = $parsed['text'] ?? '';
                            $msgImages = $parsed['images'] ?? [];

                            // 如果有图片，需要构建多模态格式
                            if (!empty($msgImages)) {
                                $contentParts = [];
                                foreach ($msgImages as $imageUrl) {
                                    $contentParts[] = [
                                        'type' => 'image',
                                        'file_url' => $imageUrl
                                    ];
                                }
                                if (!empty($msgContent)) {
                                    $contentParts[] = [
                                        'type' => 'text',
                                        'text' => $msgContent
                                    ];
                                }
                                $msgContent = json_encode($contentParts, JSON_UNESCAPED_SLASHES);
                                $msgContentType = 'object_string';
                            }
                        }
                    }

                    $historyMessages[] = [
                        'role' => $msg['role'],
                        'content' => $msgContent,
                        'content_type' => $msgContentType
                    ];
                }
            }

            // 用于存储完整消息
            $completeMessage = '';
            $newConversationId = $cozeConversationId;

            // 调用流式API，传入回调函数处理每个数据块
            $result = $cozeService->chatStream(
                (string)$userId,
                $content,
                $images,
                $cozeConversationId,
                function ($contentChunk, $jsonData) use (&$completeMessage, &$newConversationId) {
                    $completeMessage .= $contentChunk;

                    // 记录接收到的内容块
                    \think\facade\Log::info('接收到内容块: ' . $contentChunk);

                    // 实时输出到前端
                    echo "data: " . json_encode([
                        'type' => 'message',
                        'content' => $contentChunk
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";

                    // 强制刷新输出缓冲区
                    flush();

                    // 记录已刷新
                    \think\facade\Log::info('已刷新输出缓冲区');
                },
                $historyMessages  // 传递历史消息
            );

            // 更新会话的conversation_id
            if (!$conversation->conversation_id && $result['conversation_id']) {
                $conversation->conversation_id = $result['conversation_id'];
            }

            // 保存AI回复
            $assistantMessage = AiMessage::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'role' => 'assistant',
                'content' => $result['message'],
                'content_type' => 'text'
            ]);

            // 更新会话消息数量
            $conversation->message_count = AiMessage::where('conversation_id', $conversation->id)->count();
            $conversation->save();

            // 发送完成信号
            echo "data: " . json_encode([
                'type' => 'done',
                'assistant_message' => [
                    'id' => $assistantMessage->id,
                    'content' => $result['message']
                ]
            ], JSON_UNESCAPED_UNICODE) . "\n\n";

            flush();
        } catch (\Exception $e) {
            \think\facade\Log::error('流式调用失败：' . $e->getMessage());
            echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";

            flush();
        }
    }

    /**
     * 获取会话列表
     */
    public function getConversations()
    {
        try {
            $userId = $this->request->userId;
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            $query = AiConversation::where('user_id', $userId)
                ->order('updated_at', 'desc');

            $conversations = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page
            ]);

            $list = [];
            foreach ($conversations->items() as $conversation) {
                // 获取最后一条消息
                $lastMessage = AiMessage::where('conversation_id', $conversation->id)
                    ->order('created_at', 'desc')
                    ->find();

                $list[] = [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'message_count' => $conversation->message_count,
                    'last_message' => $lastMessage ? $lastMessage->content : '',
                    'last_message_time' => $lastMessage ? $lastMessage->created_at : $conversation->created_at,
                    'status' => $conversation->status,
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at
                ];
            }

            return Response::success([
                'list' => $list,
                'total' => $conversations->total(),
                'page' => $page,
                'page_size' => $pageSize
            ]);
        } catch (\Exception $e) {
            return Response::error('获取会话列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取会话消息列表
     */
    public function getMessages()
    {
        try {
            $userId = $this->request->userId;
            $conversationId = $this->request->param('conversation_id');
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 50);

            if (!$conversationId) {
                return Response::validateError('会话ID不能为空');
            }

            // 验证会话是否属于当前用户
            $conversation = AiConversation::where('id', $conversationId)
                ->where('user_id', $userId)
                ->find();

            if (!$conversation) {
                return Response::error('会话不存在');
            }

            // 获取消息列表
            $query = AiMessage::where('conversation_id', $conversationId)
                ->order('created_at', 'asc');

            $messages = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page
            ]);

            $list = [];
            foreach ($messages->items() as $message) {
                $messageData = [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'content_type' => $message->content_type,
                    'created_at' => $message->created_at
                ];

                // 如果是图文混合消息，解析 JSON
                if ($message->content_type === 'mixed') {
                    $parsedContent = json_decode($message->content, true);
                    if ($parsedContent) {
                        $messageData['text'] = $parsedContent['text'] ?? '';
                        $messageData['images'] = $parsedContent['images'] ?? [];
                    }
                }

                $list[] = $messageData;
            }

            return Response::success([
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'message_count' => $conversation->message_count
                ],
                'messages' => $list,
                'total' => $messages->total(),
                'page' => $page,
                'page_size' => $pageSize
            ]);
        } catch (\Exception $e) {
            return Response::error('获取消息列表失败：' . $e->getMessage());
        }
    }

    /**
     * 删除会话
     */
    public function deleteConversation()
    {
        try {
            $userId = $this->request->userId;
            $conversationId = $this->request->param('id');

            if (!$conversationId) {
                return Response::validateError('会话ID不能为空');
            }

            $conversation = AiConversation::where('id', $conversationId)
                ->where('user_id', $userId)
                ->find();

            if (!$conversation) {
                return Response::error('会话不存在');
            }

            // 开始事务
            Db::startTrans();
            try {
                // 删除会话消息
                AiMessage::where('conversation_id', $conversationId)->delete();

                // 删除会话
                $conversation->delete();

                Db::commit();
                return Response::success([], '删除成功');
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('删除会话失败：' . $e->getMessage());
        }
    }

    /**
     * 更新会话标题
     */
    public function updateConversationTitle()
    {
        try {
            $userId = $this->request->userId;
            $conversationId = $this->request->param('id');
            $title = $this->request->param('title');

            if (!$conversationId) {
                return Response::validateError('会话ID不能为空');
            }

            if (empty($title)) {
                return Response::validateError('标题不能为空');
            }

            $conversation = AiConversation::where('id', $conversationId)
                ->where('user_id', $userId)
                ->find();

            if (!$conversation) {
                return Response::error('会话不存在');
            }

            $conversation->title = $title;
            $conversation->save();

            return Response::success([
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title
                ]
            ], '更新成功');
        } catch (\Exception $e) {
            return Response::error('更新标题失败：' . $e->getMessage());
        }
    }

    /**
     * 清空所有会话
     */
    public function clearAllConversations()
    {
        try {
            $userId = $this->request->userId;

            // 开始事务
            Db::startTrans();
            try {
                // 获取用户所有会话ID
                $conversationIds = AiConversation::where('user_id', $userId)->column('id');

                if (!empty($conversationIds)) {
                    // 删除所有消息
                    AiMessage::whereIn('conversation_id', $conversationIds)->delete();

                    // 删除所有会话
                    AiConversation::where('user_id', $userId)->delete();
                }

                Db::commit();
                return Response::success([], '清空成功');
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return Response::error('清空会话失败：' . $e->getMessage());
        }
    }
}
