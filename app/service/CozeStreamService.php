<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Log;

/**
 * Coze Stream Run API 服务类
 * 使用新的 stream_run 接口
 */
class CozeStreamService
{
    private $apiKey;
    private $projectId;
    private $apiUrl;

    public function __construct()
    {
        // 从环境变量读取
        $this->apiKey = env('coze.api_key', '');
        $this->projectId = env('coze.project_id', '');
        $this->apiUrl = env('coze.stream_run_url', 'https://znp8dbgtb7.coze.site/stream_run');

        // 打印配置信息用于调试
        Log::info('CozeStream配置: API Key=' . substr($this->apiKey, 0, 20) . '..., Project ID=' . $this->projectId);
    }

    /**
     * 发起对话（流式响应）
     * @param string $content 用户消息内容
     * @param array $images 图片URL数组
     * @param string|null $sessionId 会话ID（可选，用于继续对话）
     * @return array
     */
    public function chat(string $content, array $images = [], ?string $sessionId = null)
    {
        try {
            // 如果没有提供 session_id，生成一个随机的
            if (!$sessionId) {
                $sessionId = $this->generateSessionId();
            }

            // 构建 prompt 数组
            $prompt = [];

            // 添加文本内容
            if (!empty($content)) {
                $prompt[] = [
                    'type' => 'text',
                    'content' => [
                        'text' => $content
                    ]
                ];
            }

            // 添加图片
            foreach ($images as $imageUrl) {
                $prompt[] = [
                    'type' => 'image_url',
                    'content' => [
                        'image_url' => $imageUrl
                    ]
                ];
            }

            // 构建请求参数
            $params = [
                'content' => [
                    'query' => [
                        'prompt' => $prompt
                    ]
                ],
                'type' => 'query',
                'session_id' => $sessionId,
                'project_id' => $this->projectId
            ];

            Log::info('CozeStream请求参数: ' . json_encode($params, JSON_UNESCAPED_UNICODE));

            // 发起请求
            $ch = curl_init($this->apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            // 开发环境禁用SSL验证
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // 记录原始响应
            Log::info('CozeStream原始响应: ' . substr($response, 0, 2000));

            if ($error) {
                throw new \Exception('CURL Error: ' . $error);
            }

            if ($httpCode !== 200) {
                throw new \Exception('HTTP Error: ' . $httpCode . ', Response: ' . $response);
            }

            // 解析流式响应
            $result = $this->parseStreamResponse($response);
            $result['session_id'] = $sessionId;

            Log::info('CozeStream解析结果: ' . json_encode($result, JSON_UNESCAPED_UNICODE));

            return $result;
        } catch (\Exception $e) {
            Log::error('CozeStream API调用失败：' . $e->getMessage());
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
        $completeMessage = '';
        $events = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // 跳过空行和 event: 行
            if (empty($line) || strpos($line, 'event:') === 0) {
                continue;
            }

            // 解析 data: 开头的行
            if (strpos($line, 'data:') === 0) {
                $data = trim(substr($line, 5));

                // 跳过特殊标记
                if ($data === '[DONE]' || $data === '[START]') {
                    continue;
                }

                $jsonData = json_decode($data, true);
                if ($jsonData) {
                    $events[] = $jsonData;

                    // 提取消息内容 - 新格式使用 content.answer
                    if (isset($jsonData['content']['answer']) && is_string($jsonData['content']['answer'])) {
                        $completeMessage .= $jsonData['content']['answer'];
                    }

                    // 检查错误
                    if (isset($jsonData['content']['error']) && $jsonData['content']['error']) {
                        throw new \Exception('API Error: ' . json_encode($jsonData['content']['error'], JSON_UNESCAPED_UNICODE));
                    }
                }
            }
        }

        return [
            'message' => $completeMessage,
            'events' => $events
        ];
    }

    /**
     * 生成随机会话ID
     * @return string
     */
    private function generateSessionId(): string
    {
        // 生成一个类似 "zBLbREw4WEOactPOUmLQe" 的随机ID
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $sessionId = '';
        for ($i = 0; $i < 21; $i++) {
            $sessionId .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $sessionId;
    }
}
