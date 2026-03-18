<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Log;

/**
 * AI行情预测服务
 */
class AIMarketService
{
    private $apiUrl;
    private $apiKey;
    private $botId;

    public function __construct()
    {
        $this->apiUrl = config('coze.api_url');
        $this->apiKey = config('coze.api_key');
        $this->botId = config('coze.market_bot_id'); // 使用专门的行情预测Bot ID
    }

    /**
     * 分析市场行情
     * 
     * @param array $marketData 市场数据
     * @return array 分析结果
     */
    public function analyzeMarket(array $marketData): array
    {
        try {
            // 构建系统提示词
            $systemPrompt = $this->getSystemPrompt();

            // 构建用户输入
            $userPrompt = $this->buildUserPrompt($marketData);

            // 调用Coze API
            $response = $this->callCozeAPI($systemPrompt, $userPrompt);

            // 解析响应
            $result = $this->parseResponse($response);

            return $result;
        } catch (\Exception $e) {
            Log::error('AI市场分析失败：' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取系统提示词
     */
    private function getSystemPrompt(): string
    {
        return <<<PROMPT
# 农产品行情预测分析师

你是一位专业的农产品市场分析师，拥有丰富的农业电商行业经验。

## 分析任务
基于提供的店铺和平台销售数据，进行深度市场分析，并按照以下JSON格式输出：

```json
{
  "metrics": {
    "market_heat": 85,
    "competition_index": 72,
    "growth_potential": 78
  },
  "analysis": "详细的行情分析内容，markdown语法内容（200-400字）",
  "suggestions": "## 调整建议\n\n1. **产品优化**：具体建议内容\n2. **价格策略**：具体建议内容\n3. **库存管理**：具体建议内容",
  "risks": [
    "风险提示1：具体描述",
    "风险提示2：具体描述"
  ]
}
```

## 指标说明

**market_heat (市场热度 0-100)**
- 综合平台整体销售趋势、订单增长率
- 评估当前市场活跃程度
- 考虑季节性因素

**competition_index (竞争指数 0-100)**
- 对比店铺与平台平均销售水平
- 分析热销产品竞争激烈程度
- 评估市场饱和度

**growth_potential (增长潜力 0-100)**
- 基于店铺销售趋势
- 评估品类结构合理性
- 预测未来增长空间

## 分析要点

1. **行情分析**：总结店铺表现、热销产品、品类结构、市场趋势
2. **调整建议**：提供3-5条具体可执行的建议（产品、价格、库存、营销），使用Markdown格式，包含标题和列表
3. **风险提示**：提供2-4条风险预警（市场、运营、季节性）

## 输出要求
- 必须严格按照JSON格式输出
- 指标必须是0-100之间的整数
- 分析内容要专业但易懂
- suggestions字段必须是Markdown格式的字符串，包含标题、列表、加粗等格式
- 建议要具体可执行
- 使用简体中文
PROMPT;
    }

    /**
     * 构建用户输入提示词
     */
    private function buildUserPrompt(array $marketData): string
    {
        $dataJson = json_encode($marketData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
请基于以下数据进行农产品行情预测分析：

{$dataJson}

请严格按照JSON格式输出分析结果，不要包含任何其他文字说明。
PROMPT;
    }

    /**
     * 调用Coze API
     */
    private function callCozeAPI(string $systemPrompt, string $userPrompt): string
    {
        // 构建请求参数 - 使用非流式响应
        $params = [
            'bot_id' => $this->botId,
            'user_id' => 'market_forecast_' . time(),
            'stream' => false,
            'auto_save_history' => true,
            'additional_messages' => [
                [
                    'role' => 'user',
                    'content' => $systemPrompt . "\n\n" . $userPrompt,
                    'content_type' => 'text'
                ]
            ]
        ];

        Log::info('AI市场分析请求参数: ' . json_encode($params, JSON_UNESCAPED_UNICODE));

        // 发起请求，等待完成
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 增加超时时间，等待AI完成
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('CURL Error: ' . $error);
        }

        if ($httpCode !== 200) {
            Log::error('AI API错误响应: ' . $response);
            throw new \Exception('HTTP Error: ' . $httpCode);
        }

        Log::info('AI对话响应: ' . $response);

        $responseData = json_decode($response, true);
        if (!$responseData || !isset($responseData['data'])) {
            throw new \Exception('响应格式错误');
        }

        $chatId = $responseData['data']['id'];
        $conversationId = $responseData['data']['conversation_id'];
        $status = $responseData['data']['status'] ?? '';

        // 如果状态是 in_progress，需要轮询等待完成
        if ($status === 'in_progress') {
            Log::info('AI正在处理中，开始轮询状态...');
            $status = $this->waitForCompletion($conversationId, $chatId);
        }

        // 检查最终状态
        if ($status !== 'completed') {
            $errorMsg = $responseData['data']['last_error']['msg'] ?? '未知错误';
            throw new \Exception('AI分析失败: ' . $errorMsg);
        }

        // 获取消息列表
        return $this->getMessages($conversationId, $chatId);
    }

    /**
     * 等待对话完成
     */
    private function waitForCompletion(string $conversationId, string $chatId, int $maxAttempts = 60): string
    {
        $retrieveUrl = "https://api.coze.cn/v3/chat/retrieve?conversation_id={$conversationId}&chat_id={$chatId}";

        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep(1); // 每1秒检查一次

            $ch = curl_init($retrieveUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                Log::error('轮询状态失败: ' . $response);
                continue;
            }

            $data = json_decode($response, true);
            if (!$data || !isset($data['data'])) {
                continue;
            }

            $status = $data['data']['status'] ?? '';
            Log::info("轮询第 " . ($i + 1) . " 次，状态: {$status}");

            if ($status === 'completed') {
                Log::info('AI分析完成');
                return 'completed';
            }

            if ($status === 'failed' || $status === 'requires_action') {
                $errorMsg = $data['data']['last_error']['msg'] ?? '未知错误';
                throw new \Exception('AI处理失败: ' . $errorMsg);
            }
        }

        throw new \Exception('AI处理超时，请稍后重试');
    }

    /**
     * 获取对话消息
     */
    private function getMessages(string $conversationId, string $chatId): string
    {
        $messagesUrl = "https://api.coze.cn/v3/chat/message/list?conversation_id={$conversationId}&chat_id={$chatId}";

        $ch = curl_init($messagesUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('获取消息失败');
        }

        Log::info('AI消息列表响应: ' . substr($response, 0, 1000));

        return $response;
    }

    /**
     * 解析API响应
     */
    private function parseResponse(string $response): array
    {
        // 解析消息列表响应
        $responseData = json_decode($response, true);

        if (!$responseData || !isset($responseData['data'])) {
            throw new \Exception('响应解析失败');
        }

        // 提取消息内容
        $content = '';

        // 遍历消息列表，找到 assistant 的 answer 类型消息
        foreach ($responseData['data'] as $message) {
            if ($message['role'] === 'assistant' && $message['type'] === 'answer') {
                $content = $message['content'];
                break;
            }
        }

        if (empty($content)) {
            throw new \Exception('未找到AI回复内容');
        }

        Log::info('AI回复内容: ' . $content);

        // 尝试解析JSON
        $result = json_decode($content, true);

        if (!$result) {
            // 如果直接解析失败，尝试提取JSON部分
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $result = json_decode($matches[0], true);
            }
        }

        if (!$result) {
            throw new \Exception('AI返回内容格式错误');
        }

        // 验证必需字段
        if (
            !isset($result['metrics']) || !isset($result['analysis']) ||
            !isset($result['suggestions']) || !isset($result['risks'])
        ) {
            throw new \Exception('AI返回数据缺少必需字段');
        }

        // 验证指标范围
        $metrics = $result['metrics'];
        if (
            !isset($metrics['market_heat']) || !isset($metrics['competition_index']) ||
            !isset($metrics['growth_potential'])
        ) {
            throw new \Exception('指标数据不完整');
        }

        // 确保指标在0-100范围内
        $result['metrics']['market_heat'] = max(0, min(100, intval($metrics['market_heat'])));
        $result['metrics']['competition_index'] = max(0, min(100, intval($metrics['competition_index'])));
        $result['metrics']['growth_potential'] = max(0, min(100, intval($metrics['growth_potential'])));

        return $result;
    }
}
