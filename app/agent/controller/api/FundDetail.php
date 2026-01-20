<?php
declare (strict_types = 1);

namespace app\agent\controller\api;

use think\facade\Session;
use think\facade\Db;
use think\facade\Request;
use app\common\service\ApiFundDetail as S;
use app\common\model\ApiFundDetail as M;

class FundDetail extends  \app\agent\controller\Base
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
    
            if ($search = input('get.data_type')) {
                $where[] = ['data_type','in', $search];
            }
    
            if ($search = input('get.status')) {
                $where[] = ['d.status','in', $search];
            }
    
            $list = Db::name('api_fund_detail')->alias('d')->join('cloud_times_api_user u', 'd.user_id=u.id')->order('d.id','desc')->where($where)->paginate($limit)->each(function ($item){
                $user = \think\facade\Db::name('api_user')
                    ->field('*')
                    ->where('id',$item['user_id'])
                    ->find();
                $item['mobile'] = !empty($user['mobile']) ? $user['mobile'] : 0;
                
                return $item;
            });
           
            return ['code'=>0,'data'=>$list->items(),'count' => $list->total(), 'limit' => $limit];
        }
        return $this->fetch();
    }

}
