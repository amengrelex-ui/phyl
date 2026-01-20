<?php
declare (strict_types = 1);

namespace app\agent\controller\api;

use think\facade\Session;
use think\facade\Request;
use think\facade\Db;
use app\common\service\ApiFundDetail as S;
use app\common\model\ApiFundDetail as M;

class UserWithdraw extends  \app\agent\controller\Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];

    public function agent_index(){
        if (Request::isAjax()) {
            $admin = Db::name('admin_admin')->where('id', Session::get('admin.id'))->find();
            $agent_id = Db::name('api_user')->where('mobile', $admin['username'])->value('id');
            $where = [];
            $limit = input('get.limit');
            $subs = (Session::get('agent'))['subs'];
            $where[] = ['u.id', 'in', $subs];
            if ($search = input('get.mobile')) {
                $user_id = Db::name('api_user')
                    ->where('mobile', 'like', "%".$search."%")
                    ->column('id');
                if (empty($user_id)) {
                    return ['code' => 0, 'data' => [], 'extend' => ['count' => 0, 'limit' => $limit]];
                }
                $where[] = ['user_id', 'in', $user_id];
            }
            
            
             //日期查询条件
            if( $range =  input('get.range')){
                $dates = explode('~', $range);
                $where[] = ['d.create_time','>', trim($dates[0])." 00:00:00"];
                $where[] = ['d.create_time','<', trim($dates[1])." 23:59:59"];
            }
            
            $where[] = ['data_type','in', 1];
                
            if ($search = input('get.status')) {
                $where[] = ['d.status','in', $search];
            }
    
            $list = Db::name('api_fund_detail')->order('d.id','desc')->alias('d')->join('cloud_times_api_user u', 'd.user_id=u.id')->where($where)->paginate($limit)->each(function ($item){
                $user = \think\facade\Db::name('api_user')
                    ->alias('a')
                    ->field('a.*,p.account_name,p.account_number,p.name_of_deposit_bank')
                    ->join('api_account p','a.id = p.user_id')
                    ->where('a.id',$item['user_id'])
                    ->find();
                $item['mobile'] = !empty($user['mobile']) ? $user['mobile'] : 0;
                $item['account_name'] = $user['account_name'];
                $item['account_number'] = $user['account_number'];
                $item['name_of_deposit_bank'] = $user['name_of_deposit_bank'];
                return $item;
            });
            return ['code'=>0,'data'=>$list->items(),'count' => $list->total(), 'limit' => $limit];
        }
        return $this->fetch();
    }

}
