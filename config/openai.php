<?php
/**
 * OpenAI 配置文件
 */
return [
    // OpenAI API Key
    'api_key' => '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',
    
    // API 地址
    'api_url' => 'https://api.openai.com/v1/chat/completions',
    
    // 默认模型
    'model' => 'gpt-3.5-turbo',
    
    // 温度参数 (0-2)
    'temperature' => 0.7,
    
    // 最大 token 数
    'max_tokens' => 1000,
    
    // 系统提示词（医生角色）
    'system_prompt' => '你是一位专业、友善的在线医生助手。请用通俗易懂的语言回答用户的健康问题，提供专业的医疗建议。如果问题涉及严重疾病或紧急情况，请建议用户及时就医。回答要简洁明了，富有同理心。',
];
