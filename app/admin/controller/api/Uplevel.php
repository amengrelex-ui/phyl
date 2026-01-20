<?php
declare (strict_types = 1);

namespace app\admin\controller\api;

use think\facade\Request;
use think\facade\Db;

class Uplevel extends  \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];

    // 列表
    public function index(){
        if (Request::isAjax()) {
            $param = $this->request->param();
            $limit = $param['limit'];
            $list = [];
            $list = Db::name('api_user')
            ->where(function ($query) {
                $query->where('offline_auths', '>=', 500)
                    ->where('level', 4);
            })->whereOr(function ($query) {
                $query->where('offline_auths', '>=', 1000)
                    ->where('level', 3);
            })->whereOr(function ($query) {
                $query->where('offline_auths', '>=', 5000)
                    ->where('level', 2);
            })->select();
            foreach ($list as $k => $v){
                if(date('d') >=10){
                    $month_start = strtotime(date("Y-m-10 00:00:00"));
                    $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 month')));
                } else {
                    $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month')));
                    $month_end = strtotime(date("Y-m-9 23:59:59"));
                }
                $param = Db::name('api_task')->where('user_id', $v['id'])->where('month_start', $month_start)->where('month_end', $month_end)->find();
                $buy_sum = $this->get_group_buy($v['id']);
                if($param['group_buy'] > $buy_sum){
                    $buy_sum = $param['group_buy'];
                }
                if($v['level'] == 4 &&$param['offline_auths'] >= 50 && $param['recharge_buys'] >= 20 && $param['sub1_recharge'] >= 10){
                    continue;
                } else {
                    unset($list[$k]);
                }
                
                if($v['level'] == 3 && $param['offline_auths'] >= 100 && $param['sub1_buy'] >= 50){
                    continue;
                } else {
                    unset($list[$k]);
                }
                
                if($v['level'] == 2 && $param['offline_auths'] >= 200 && $param['sub1_buy'] >= 80 && $buy_sum >= 1000000){
                    continue;
                } else {
                    unset($list[$k]);
                }
                
                if($v['level'] == 1 && $param['offline_auths'] >= 200 && $param['sub1_buy'] >= 50 && $param['sub2_buy'] >= 30 && $param['sub1_special'] >= 10 && $buy_sum >= 2000000){
                    continue;
                } else {
                    unset($list[$k]);
                }
            }
            
            if(!empty($list)){
                return ['code'=>0,'data'=>$list,'extend'=>['count' => count($list), 'limit' => $limit]];
            }else {
                return ['code'=>0,'data'=>$list];
            }
        }
        return $this->fetch();
    }
    
    
    //升级
    public function distribute(){
        if(Request::isAjax()){
            $param = $this->request->param();
            $user = Db::name('api_user')->where('id',$param['id'])->find();
            if (!$user) {
                return returnJson(301, '未找到当前用户请重新登录');
            }
            $ret = false;
            if($user['invitees'] >= 500 && $user['level'] == 4){
                $ret = Db::name('api_user')->where('id', $user['id'])->update([
                    'level' => 3
                ]);
            } else if($user['invitees'] >= 1000 && $user['level'] == 3){
                $ret = Db::name('api_user')->where('id', $user['id'])->update([
                    'level' => 2
                ]);
            } else if($user['invitees'] >= 5000 && $user['level'] == 2){
                $ret = Db::name('api_user')->where('id', $user['id'])->update([
                    'level' => 1
                ]);
            }
            if($ret){
                return returnJson(200, '升级成功！');
            } else {
                return returnJson(200, '非法操作！');
            }
        }
    }
    
    public function get_group_buy($user_id, $buy_sum = 0, $level = 0){
        $list = Db::name('api_user')->field('id,mobile,create_time')->where('parent_user_id', $user_id)->select();
        $date = time();
        if(date('d') >=10){
            $month_start = date("Y-m-d H:i:s", strtotime(date("Y-m-10 00:00:00")));
            $month_end = date("Y-m-d H:i:s", strtotime(date("Y-m-9 23:59:59", strtotime('+1 month'))));
        } else {
            $month_start = date("Y-m-d H:i:s", strtotime(date("Y-m-10 00:00:00", strtotime('-1 month'))));
            $month_end = date("Y-m-d H:i:s", strtotime(date("Y-m-9 23:59:59")));
        }
        if(count($list) > 0){
            $level++;
            foreach ($list as $v){
                $v['level'] = $level;
                $buy_sum += Db::name('api_subscribe')->where('create_time', '>=', $month_start)->where('create_time', '<', $month_end)->where('user_id', $v['id'])->sum('price');
                $buy_sum = $this->get_group_buy($v['id'], $buy_sum, $level);
            }
        }
        return $buy_sum;
    }
}
