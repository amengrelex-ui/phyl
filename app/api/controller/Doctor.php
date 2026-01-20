<?php

namespace app\api\controller;

use app\common\service\OpenAiService;
use think\facade\Db;
use think\facade\Validate;
use think\Request;

class Doctor extends \app\BaseController
{
    /**
     * AI 医生问答
     */
    public function ask(Request $request)
    {
        // 验证参数
        $rule = [
            'question|问题' => ['require', 'length:1,500'],
        ];
        
        Validate::rule($rule);
        if (!Validate::check($request->param())) {
            return returnJson(400, Validate::getError());
        }
        
        $question = trim($request->param('question'));
        $user_id = isset($this->user['id']) ? $this->user['id'] : 0;
        
        // 获取历史对话（可选）
        $history = [];
        if ($request->param('session_id')) {
            $sessionId = $request->param('session_id');
            $historyRecords = Db::name('api_doctor_chat')
                ->where('session_id', $sessionId)
                ->where('user_id', $user_id)
                ->order('create_time', 'asc')
                ->limit(10)
                ->select()
                ->toArray();
            
            foreach ($historyRecords as $record) {
                $history[] = [
                    'question' => $record['question'],
                    'answer' => $record['answer']
                ];
            }
        } else {
            // 如果没有 session_id，生成一个新的
            $sessionId = md5($user_id . time() . rand(1000, 9999));
        }
        
        try {
            // 调用 OpenAI 服务
            $openAiService = new OpenAiService();
            $result = $openAiService->chat($question, $history);
            
            if (!$result['success']) {
                return returnJson(500, 'AI 服务暂时不可用，请稍后再试', [
                    'error' => $result['error'] ?? '未知错误'
                ]);
            }
            
            $answer = $result['answer'];
            
            // 保存对话记录
            if ($user_id > 0) {
                Db::name('api_doctor_chat')->insert([
                    'user_id' => $user_id,
                    'session_id' => $sessionId,
                    'question' => $question,
                    'answer' => $answer,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            }
            
            return returnJson(200, '成功', [
                'answer' => $answer,
                'session_id' => $sessionId,
                'usage' => $result['usage'] ?? []
            ]);
            
        } catch (\Exception $e) {
            return returnJson(500, '服务异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取历史对话记录
     */
    public function history(Request $request)
    {
        $user_id = isset($this->user['id']) ? $this->user['id'] : 0;
        
        if ($user_id <= 0) {
            return returnJson(401, '请先登录');
        }
        
        $sessionId = $request->param('session_id', '');
        $page = $request->param('page', 1);
        $limit = $request->param('limit', 20);
        
        $where = [
            ['user_id', '=', $user_id]
        ];
        
        if ($sessionId) {
            $where[] = ['session_id', '=', $sessionId];
        }
        
        $list = Db::name('api_doctor_chat')
            ->where($where)
            ->order('create_time', 'desc')
            ->page($page, $limit)
            ->select();
        
        $total = Db::name('api_doctor_chat')
            ->where($where)
            ->count();
        
        return returnJson(200, '成功', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }
    
    /**
     * 获取会话列表
     */
    public function sessions(Request $request)
    {
        $user_id = isset($this->user['id']) ? $this->user['id'] : 0;
        
        if ($user_id <= 0) {
            return returnJson(401, '请先登录');
        }
        
        $page = $request->param('page', 1);
        $limit = $request->param('limit', 20);
        
        // 获取每个会话的最新一条记录
        $sessions = Db::name('api_doctor_chat')
            ->where('user_id', $user_id)
            ->field('session_id, MAX(create_time) as last_time, COUNT(*) as message_count')
            ->group('session_id')
            ->order('last_time', 'desc')
            ->page($page, $limit)
            ->select();
        
        $total = Db::name('api_doctor_chat')
            ->where('user_id', $user_id)
            ->group('session_id')
            ->count();
        
        return returnJson(200, '成功', [
            'list' => $sessions,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }
    
    /**
     * 删除会话
     */
    public function deleteSession(Request $request)
    {
        $user_id = isset($this->user['id']) ? $this->user['id'] : 0;
        
        if ($user_id <= 0) {
            return returnJson(401, '请先登录');
        }
        
        $sessionId = $request->param('session_id', '');
        
        if (empty($sessionId)) {
            return returnJson(400, '会话ID不能为空');
        }
        
        $result = Db::name('api_doctor_chat')
            ->where('user_id', $user_id)
            ->where('session_id', $sessionId)
            ->delete();
        
        if ($result) {
            return returnJson(200, '删除成功');
        } else {
            return returnJson(400, '删除失败');
        }
    }
}
