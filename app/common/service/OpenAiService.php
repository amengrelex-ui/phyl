<?php
declare(strict_types=1);

namespace app\common\service;

use Exception;

/**
 * OpenAI 服务类
 */
class OpenAiService
{
    private $apiKey;
    private $apiUrl;
    private $model;
    private $temperature;
    private $maxTokens;
    private $systemPrompt;
    
    public function __construct()
    {
        // 从配置文件读取配置
        $config = config('openai', []);
        $this->apiKey = $config['api_key'] ?? '';
        $this->apiUrl = $config['api_url'] ?? 'https://api.openai.com/v1/chat/completions';
        $this->model = $config['model'] ?? 'gpt-3.5-turbo';
        $this->temperature = $config['temperature'] ?? 0.7;
        $this->maxTokens = $config['max_tokens'] ?? 1000;
        $this->systemPrompt = $config['system_prompt'] ?? '';
    }
    
    /**
     * 发送消息到 OpenAI
     * @param string $message 用户消息
     * @param array $history 历史对话记录
     * @param string $systemPrompt 系统提示词
     * @return array
     */
    public function chat(string $message, array $history = [], string $systemPrompt = ''): array
    {
        try {
            // 构建消息数组
            $messages = [];
            
            // 使用传入的系统提示词，如果没有则使用配置的默认值
            if (empty($systemPrompt)) {
                $systemPrompt = $this->systemPrompt;
            }
            
            if (!empty($systemPrompt)) {
                $messages[] = [
                    'role' => 'system',
                    'content' => $systemPrompt
                ];
            }
            
            // 添加历史对话
            foreach ($history as $item) {
                if (isset($item['question']) && isset($item['answer'])) {
                    $messages[] = [
                        'role' => 'user',
                        'content' => $item['question']
                    ];
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $item['answer']
                    ];
                }
            }
            
            // 添加当前消息
            $messages[] = [
                'role' => 'user',
                'content' => $message
            ];
            
            // 构建请求数据
            $data = [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ];
            
            // 发送请求
            $response = $this->sendRequest($data);
            
            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'error' => $response['error']['message'] ?? 'OpenAI API 请求失败',
                    'code' => $response['error']['code'] ?? 'unknown'
                ];
            }
            
            // 提取回复内容
            $answer = $response['choices'][0]['message']['content'] ?? '';
            
            return [
                'success' => true,
                'answer' => trim($answer),
                'usage' => $response['usage'] ?? []
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'exception'
            ];
        }
    }
    
    /**
     * 发送 HTTP 请求
     * @param array $data
     * @return array
     */
    private function sendRequest(array $data): array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL 错误: ' . $error);
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            return [
                'error' => [
                    'message' => $errorData['error']['message'] ?? 'HTTP 错误: ' . $httpCode,
                    'code' => $errorData['error']['code'] ?? $httpCode
                ]
            ];
        }
        
        return json_decode($response, true);
    }
}
