<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Log;

/**
 * Coze API 服务类
 */
class CozeService
{
    private $apiKey;
    private $botId;
    private $apiUrl = 'https://api.coze.cn/v3/chat';

    public function __construct()
    {
        // 从环境变量读取（ThinkPHP的env函数格式：section.key）
        $this->apiKey = env('coze.api_key', '');
        $this->botId = env('coze.bot_id', '');

        // 打印配置信息用于调试（注意：生产环境应该移除）
        Log::info('Coze配置信息: API Key=' . $this->apiKey . ', Bot ID=' . $this->botId);
    }

    /**
     * 发起对话（流式响应）
     * @param string $userId 用户ID
     * @param string $content 用户消息内容
     * @param array $images 图片URL数组
     * @param string|null $conversationId Coze会话ID（可选，用于继续对话）
     * @param array $additionalMessages 额外的上下文消息
     * @return array
     */
    public function chat(string $userId, string $content, array $images = [], ?string $conversationId = null, array $additionalMessages = [])
    {
        try {
            // 构建消息内容
            $messageContent = $content;
            $contentType = 'text';

            // 如果有图片，构建多模态消息
            if (!empty($images)) {
                $contentParts = [];

                // 添加图片（图片放在文本之前）
                foreach ($images as $imageUrl) {
                    $contentParts[] = [
                        'type' => 'image',
                        'file_url' => $imageUrl
                    ];
                }

                // 添加文本
                if (!empty($content)) {
                    $contentParts[] = [
                        'type' => 'text',
                        'text' => $content
                    ];
                }

                $messageContent = json_encode($contentParts, JSON_UNESCAPED_SLASHES);
                $contentType = 'object_string';
            }

            // 构建请求参数
            $params = [
                'bot_id' => $this->botId,
                'user_id' => $userId,
                'stream' => true,
                'auto_save_history' => true,
                'additional_messages' => array_merge($additionalMessages, [
                    [
                        'role' => 'user',
                        'content' => $messageContent,
                        'content_type' => $contentType
                    ]
                ])
            ];

            // 如果有会话ID，添加到参数中
            if ($conversationId) {
                $params['conversation_id'] = $conversationId;
            }

            Log::info('Coze API请求参数: ' . json_encode($params, JSON_UNESCAPED_UNICODE));

            // 构建URL
            $url = $this->apiUrl;

            // 发起请求
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            // 开发环境禁用SSL验证（生产环境应该移除或配置正确的证书）
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // 记录原始响应用于调试
            Log::info('Coze API原始响应: ' . substr($response, 0, 1000));

            if ($error) {
                throw new \Exception('CURL Error: ' . $error);
            }

            if ($httpCode !== 200) {
                throw new \Exception('HTTP Error: ' . $httpCode . ', Response: ' . $response);
            }

            // 解析流式响应
            $result = $this->parseStreamResponse($response);

            // 记录解析结果
            Log::info('Coze API解析结果: ' . json_encode($result, JSON_UNESCAPED_UNICODE));

            return $result;
        } catch (\Exception $e) {
            Log::error('Coze API调用失败：' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 流式调用（用于实时输出）
     * @param string $userId 用户ID
     * @param string $content 用户消息内容
     * @param array $images 图片URL数组
     * @param string|null $conversationId Coze会话ID
     * @param callable $callback 回调函数，用于处理每个流式数据块
     * @param array $historyMessages 历史消息数组
     * @return array 返回会话ID和完整消息
     */
    public function chatStream(string $userId, string $content, array $images = [], ?string $conversationId = null, callable $callback = null, array $historyMessages = [])
    {
        try {
            // 构建消息内容
            $messageContent = $content;
            $contentType = 'text';

            // 如果有图片，构建多模态消息
            if (!empty($images)) {
                $contentParts = [];

                // 添加图片（图片放在文本之前）
                foreach ($images as $imageUrl) {
                    $contentParts[] = [
                        'type' => 'image',
                        'file_url' => $imageUrl
                    ];
                }

                // 添加文本
                if (!empty($content)) {
                    $contentParts[] = [
                        'type' => 'text',
                        'text' => $content
                    ];
                }

                $messageContent = json_encode($contentParts, JSON_UNESCAPED_SLASHES);
                $contentType = 'object_string';
            }

            // 构建请求参数
            $params = [
                'bot_id' => $this->botId,
                'user_id' => $userId,
                'stream' => true,
                'auto_save_history' => true,
                'additional_messages' => array_merge(
                    $historyMessages,  // 添加历史消息
                    [
                        [
                            'role' => 'user',
                            'content' => $messageContent,
                            'content_type' => $contentType
                        ]
                    ]
                )
            ];

            // 如果有会话ID，添加到参数中
            if ($conversationId) {
                $params['conversation_id'] = $conversationId;
            }

            Log::info('Coze API流式请求参数: ' . json_encode($params, JSON_UNESCAPED_UNICODE));

            // 构建URL
            $url = $this->apiUrl;

            // 发起请求
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            // 用于存储完整消息和会话ID
            $completeMessage = '';
            $newConversationId = $conversationId;

            // 设置回调函数处理流式数据
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) use (&$completeMessage, &$newConversationId, $callback) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    // 解析事件类型
                    if (strpos($line, 'event:') === 0) {
                        continue;
                    }

                    if (strpos($line, 'data:') === 0) {
                        $jsonStr = trim(substr($line, 5));

                        // 跳过 [DONE] 标记
                        if ($jsonStr === '[DONE]') {
                            continue;
                        }

                        $jsonData = json_decode($jsonStr, true);
                        if ($jsonData) {
                            // 提取会话ID
                            if (isset($jsonData['conversation_id'])) {
                                $newConversationId = $jsonData['conversation_id'];
                            }

                            // 只处理 answer 类型的消息（实际的AI回复内容）
                            // 过滤掉 verbose（元数据）、follow_up（推荐问题）等事件
                            if (isset($jsonData['type']) && $jsonData['type'] === 'answer') {
                                if (isset($jsonData['content']) && is_string($jsonData['content'])) {
                                    $content = $jsonData['content'];

                                    // 检查是否包含 created_at 字段
                                    // 如果有 created_at，说明这是最后的完整消息，不需要累加
                                    // 只累加增量内容（单个字符或词）
                                    if (!isset($jsonData['created_at'])) {
                                        $completeMessage .= $content;

                                        // 调用回调函数
                                        if ($callback) {
                                            $callback($content, $jsonData);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                return strlen($data);
            });

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception('CURL Error: ' . $error);
            }

            if ($httpCode !== 200) {
                throw new \Exception('HTTP Error: ' . $httpCode);
            }

            return [
                'conversation_id' => $newConversationId,
                'message' => $completeMessage
            ];
        } catch (\Exception $e) {
            Log::error('Coze API流式调用失败：' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 解析流式响应
     * @param string $response
     * @return array
     */
    private function parseStreamResponse(string $response)
    {
        $lines = explode("\n", $response);
        $events = [];
        $chatId = null;
        $conversationId = null;
        $completeMessage = '';
        $lastError = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // 跳过空行
            if (empty($line)) {
                continue;
            }

            // 解析事件行
            if (strpos($line, 'event:') === 0) {
                $event = trim(substr($line, 6));
                $events[] = ['event' => $event];
                continue;
            }

            // 解析数据行
            if (strpos($line, 'data:') === 0) {
                $data = trim(substr($line, 5));

                // 跳过 [DONE] 标记
                if ($data === '[DONE]') {
                    continue;
                }

                $jsonData = json_decode($data, true);
                if ($jsonData) {
                    // 提取会话ID和对话ID
                    if (isset($jsonData['conversation_id'])) {
                        $conversationId = $jsonData['conversation_id'];
                    }
                    if (isset($jsonData['id'])) {
                        $chatId = $jsonData['id'];
                    }

                    // 检查是否有错误
                    if (isset($jsonData['last_error']) && $jsonData['last_error']['code'] != 0) {
                        $lastError = $jsonData['last_error'];
                    }

                    // 只拼接 answer 类型的增量消息内容
                    // 忽略包含 created_at 的完整消息（避免重复）
                    if (isset($jsonData['type']) && $jsonData['type'] === 'answer') {
                        if (isset($jsonData['content']) && !isset($jsonData['created_at'])) {
                            $completeMessage .= $jsonData['content'];
                        }
                    }

                    // 保存最后一个事件的数据
                    if (!empty($events)) {
                        $events[count($events) - 1]['data'] = $jsonData;
                    }
                }
            }
        }

        // 如果有错误，抛出异常
        if ($lastError) {
            throw new \Exception('Coze API Error [' . $lastError['code'] . ']: ' . $lastError['msg']);
        }

        return [
            'chat_id' => $chatId,
            'conversation_id' => $conversationId,
            'message' => $completeMessage,
            'events' => $events
        ];
    }
}
