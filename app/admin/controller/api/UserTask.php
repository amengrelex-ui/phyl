<?php
namespace app\admin\controller\api;

use think\facade\Db;
use think\facade\Request;

class UserTask extends  \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];

    // 列表
    public function index(){
        if (Request::isAjax()) {
            $param = $this->request->param();
            $limit = $param['limit'];
            $where[] = ['u.level', '>', 0];
            //手机号查询条件
            if(!empty($param['mobile'])){
                $where[] = ['u.mobile', '=', $param['mobile']];
            } 
            //日期查询条件
            if(!empty($param['range'])){
                $dates = explode('~', $param['range']);
                $where[] = ['t.month_start', '=', strtotime($dates[0])];
                $where[] = ['t.month_end', '=', strtotime($dates[1])];
            } else {
                $date = time();
                if(date('d') >=10){
                    // echo "if";
                    $month_start = strtotime(date("Y-m-10 00:00:00"));
                    // echo  date("Y-m-d",$month_start);
                    $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 month', strtotime(date('Y-m-1')))));
                    // echo  date("Y-m-d",$month_end);
                } else {
                    // echo  "else";
                    $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month', strtotime(date('Y-m-1')))));
                    $month_end = strtotime(date("Y-m-9 23:59:59"));
                }
                $where[] = ['t.month_start', '=', $month_start];
                $where[] = ['t.month_end', '=', $month_end];
            }
            
            
            
            
            $list = Db::name('api_task')->alias('t')->join('cloud_times_api_user u', 't.user_id = u.id')->field('u.mobile,u.parent_user_id,u.invitees,u.price,u.level,t.*')->where($where)
            // ->where(function($query){
                //  $query->whereOr('status1', '=', 1);
                //  $query->whereOr('status2', '=', 1);
                //  $query->whereOr('status3', '=', 1);
                //  $query->whereOr('status4', '=', 1);
            //  })
             ->paginate($limit)
            ->each(function ($item) {
                $item['month_start'] = date('Y-m-d H:i:s', $item['month_start']);
                $item['month_end'] = date('Y-m-d H:i:s', $item['month_end']);
                $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
                if($item['level'] == 1){
                    if($item['status1'] == 1){
                        $item['status_txt'] = '已领取';
                        $item['status'] = 1;
                    } else if($item['status1'] == 2){
                        $item['status_txt'] = '已派发';
                        $item['status'] = 2;
                    }
                }
                if($item['level'] == 2){
                    if($item['status2'] == 1){
                        $item['status_txt'] = '已领取';
                        $item['status'] = 1;
                    } else if($item['status2'] == 2){
                        $item['status_txt'] = '已派发';
                        $item['status'] = 2;
                    }
                }
                if($item['level'] == 3){
                    if($item['status3'] == 1){
                        $item['status_txt'] = '已领取';
                        $item['status'] = 1;
                    } else if($item['status3'] == 2){
                        $item['status_txt'] = '已派发';
                        $item['status'] = 2;
                    }
                }
                if($item['level'] == 4){
                    if($item['status4'] == 1){
                        $item['status_txt'] = '已领取';
                        $item['status'] = 1;
                    } else if($item['status4'] == 2){
                        $item['status_txt'] = '已派发';
                        $item['status'] = 2;
                    }
                }
                return $item;
            });
            
            //  echo   Db::name('api_task')->getLastSql();exit();
            return ['code'=>0,'data'=>$list->items(), 'count'=> $list->total(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
        }
        
        
        return $this->fetch();
    }
    
    public function get_group_buy($user_id, $buy_sum = 0, $level = 0){
        $list = Db::name('api_user')->field('id,mobile,create_time')->where('parent_user_id', $user_id)->select();
        $date = time();
        if(date('d') >=10){
            $month_start = strtotime(date("Y-m-10 00:00:00"));
            $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 months', strtotime(date('Y-m-1')))));
        } else {
            $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month', strtotime(date('Y-m-1')))));
            $month_end = strtotime(date("Y-m-9 23:59:59"));
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
    
    public function edit(){
        $user = Db::name('api_user')->where('id', $this->request->param('user_id'))->find();
        if(date('d') >=10){
            $month_start = strtotime(date("Y-m-10 00:00:00"));
            $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 months', strtotime(date('Y-m-1')))));
        } else {
            $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month', strtotime(date('Y-m-1')))));
            $month_end = strtotime(date("Y-m-9 23:59:59"));
        }
        if (Request::isAjax()) {
            $param = $this->request->param();
            unset($param['user_id']);
            $ret = Db::name('api_task')->where('user_id', $user['id'])->where('month_start', $month_start)->where('month_end', $month_end)->update($param);
            if($ret){
                return ['code'=>200,'msg'=>'编辑成功'];
            } else {
                return ['code'=>201,'msg'=>'编辑失败'];
            }
        }
        $list = Db::name('api_task')->where('user_id', $user['id'])->where('month_start', $month_start)->where('month_end', $month_end)->find();
        $buy_sum = $this->get_group_buy($user['id']);
        $param = [];
        if($user['level'] == 4){
            if($list['offline_auths'] >= 50){
                $param['offline_auths'] = 100;
            } else {
                $param['offline_auths'] = $list['offline_auths']/50*100;
            }
            if($list['recharge_buys'] >= 20){
                $param['recharge_buys'] = 100;
            } else {
                $param['recharge_buys'] = $list['recharge_buys']/20*100;
            }
            if($list['sub1_recharge'] >= 10){
                $param['sub1_recharge'] = 100;
            } else {
                $param['sub1_recharge'] = $list['sub1_recharge']/10*100;
            }
        } else if($user['level'] == 3){
            if($list['offline_auths'] >= 100){
                $param['offline_auths'] = 100;
            } else {
                $param['offline_auths'] = $list['offline_auths']/100*100;
            }
            if($list['sub1_buy'] >= 50){
                $param['sub1_buy'] = 100;
            } else {
                $param['sub1_buy'] = $list['sub1_buy']/50*100;
            }
        } else if($user['level'] == 2){
            if($list['offline_auths'] >= 200){
                $param['offline_auths'] = 100;
            } else {
                $param['offline_auths'] = $list['offline_auths']/200*100;
            }
            if($list['sub1_buy'] >= 80){
                $param['sub1_buy'] = 100;
            } else {
                $param['sub1_buy'] = $list['sub1_buy']/80;
            }
            if($buy_sum >= 1000000){
                $param['group_buy'] = 100;
            } else {
                $param['group_buy'] = $buy_sum/1000000*100;
            }
        } else if($user['level'] == 1){
            if($list['offline_auths'] >= 200){
                $param['offline_auths'] = 100;
            } else {
                $param['offline_auths'] = $list['offline_auths']/200*100;
            }
            if($list['sub1_buy'] >= 50){
                $param['sub1_buy'] = 100;
            } else {
                $param['sub1_buy'] = $list['sub1_buy']/50*100;
            }
            if($list['sub2_buy'] >= 30){
                $param['sub2_buy'] = 100;
            } else {
                $param['sub2_buy'] = $list['sub2_buy']/30*100;
            }
            if($list['sub1_special'] >= 10){
                $param['sub1_special'] = 100;
            } else {
                $param['sub1_special'] = $list['sub1_special']/10*100;
            }
            if($buy_sum >= 2000000){
                $param['group_buy'] = 100;
            } else {
                $param['group_buy'] = $buy_sum/2000000*100;
            }
        }
        $this->assign('param', $param);
        $this->assign('list', $list);
        $this->assign('user', $user);
        return $this->fetch();
    }
    
    public function distribute(){
        $param = $this->request->param();
        $id = $param['id'];
        $user_id = $param['user_id'];
        $user = Db::name('api_user')->where('id', $user_id)->find();
        if($user['task_price'] == 0){
            return 0;
        }
        Db::startTrans();
        try {
            $res = false;
            if($user['level'] == 1){
                $res = Db::name('api_task')->where('id', $id)->update([
                    'status1' => 2
                ]);
            }
            if($user['level'] == 2){
                $res = Db::name('api_task')->where('id', $id)->update([
                    'status2' => 2
                ]);
            }
            if($user['level'] == 3){
                $res = Db::name('api_task')->where('id', $id)->update([
                    'status3' => 2
                ]);
            }
            if($user['level'] == 4){
                $res = Db::name('api_task')->where('id', $id)->update([
                    'status4' => 2
                ]);
            }
            Db::name('api_user')->where('id', $user_id)->dec('task_price', $user['task_price'])->update();
            if($res){
                Db::commit();
                return 1;
            } else {
                Db::rollback();
                return 0;
            }
        } catch (\Exception $e) {
            Db::rollback();
            return 0;
        }
    }
}