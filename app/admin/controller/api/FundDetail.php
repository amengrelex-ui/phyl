<?php
declare (strict_types = 1);

namespace app\admin\controller\api;

use think\facade\Session;
use think\facade\Db;
use think\facade\Request;
use app\common\service\ApiFundDetail as S;
use app\common\model\ApiFundDetail as M;

class FundDetail extends  \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];

    // 列表
    public function index(){
        if (Request::isAjax()) {
            return $this->getJson(M::getList());
        }
        return $this->fetch();
    }

    public function exchange(){
        if (Request::isAjax()) {
            return $this->getJson(M::getExchangeList());
        }
        return $this->fetch();
    }


    
    public function agent_index(){
        if (Request::isAjax()) {
            $admin = Db::name('admin_admin')->where('id', Session::get('admin.id'))->find();
            $agent_id = Db::name('api_user')->where('mobile', $admin['username'])->value('id');
            $where = [];
            $limit = input('get.limit');
            $where[] = ['agent_id', '=', $agent_id];
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

    // 拒绝提现申请
    public function refuse(){
        $id = $this->request->param('id');
        if($this->request->isAjax()){
            $reason = $this->request->param('reason');
            $result = Db::name('api_fund_detail')
            ->where('id',$id)
            ->find();
            $info = $result;
            if (!$result){
                return $this->getJson(['msg'=>'数据不存在','code'=>201]);
            }

            if ($result['status'] != 3){
                return $this->getJson(['msg'=>'已处理','code'=>201]);
            }

            if ($result['data_type'] == 1){
                $result = Db::name('api_user')
                ->where('id',$result['user_id'])
                ->inc('price',(float)$result['price'])
                ->update();
                if (!$result){
                    return $this->getJson(['msg'=>'金额退回失败','code'=>201]);
                }
            }elseif($result['data_type'] == 25){
                $result = Db::name('api_user')
                ->where('id',$result['user_id'])
                ->inc('pension_price',(float)$result['price'])
                ->update();
                if (!$result){
                    return $this->getJson(['msg'=>'金额退回失败','code'=>201]);
                }
                if($info['eids']){
                    Db::name('api_subscribe')->whereIn('id',$info['eids'])->data(['update_time' =>'0000-00-00 00:00:00'])->update();
                }
            }
            else{
                $result = Db::name('api_user')
                    ->where('id',$result['user_id'])
                    ->inc('price',(float)$result['price'])
                    ->update();

                if (!$result){
                    return $this->getJson(['msg'=>'金额发放失败','code'=>201]);
                }
            }
            $result = Db::name('api_fund_detail')
                ->where('id',$id)
                ->update([
                    'status' => 2,
                    'reason' => $reason
                ]);
            if (!$result){
                return $this->getJson(['msg'=>'处理失败','code'=>201]);
            }

            return $this->getJson(['msg'=>'成功','code'=>200]);
        }
        $list = Db::name('api_fund_detail')->where('id', $id)->find();
        $user = Db::name('api_user')->where('id', $list['user_id'])->find();
        $this->assign('list', $list);
        $this->assign('user', $user);
        return $this->fetch();
    }

    // 添加
    public function add(){
        if (Request::isAjax()) {
            return $this->getJson(S::goAdd(Request::post()));
        }
        return $this->fetch();
    }

    public function data_success($id)
    {
        $result = Db::name('api_fund_detail')
            ->where('id',$id)
            ->find();

        if (!$result){
            return $this->getJson(['msg'=>'数据不存在','code'=>201]);
        }

        if ($result['status'] != 3){
            return $this->getJson(['msg'=>'已处理','code'=>201]);
        }

        if ($result['data_type'] == 2){
            $result = Db::name('api_user')
                ->where('id',$result['user_id'])
                ->inc('price',(float)$result['price'])
                ->inc('grow_up',(int)$result['price'])
                ->update();
            if (!$result){
                return $this->getJson(['msg'=>'金额发放失败','code'=>201]);
            }
        }elseif($result['data_type'] == 25){
            $result = Db::name('api_user')
                ->where('id',$result['user_id'])
                ->inc('huimin_price',(float)$result['price'])
                ->update();
            if (!$result){
                return $this->getJson(['msg'=>'金额发放失败','code'=>201]);
            }
        }
        $result = Db::name('api_fund_detail')
            ->where('id',$id)
            ->update([
                'status' => 1
            ]);
        if (!$result){
            return $this->getJson(['msg'=>'处理失败','code'=>201]);
        }

        return $this->getJson(['msg'=>'成功','code'=>200]);
    }

    public function data_refuse($id)
    {
        $result = Db::name('api_fund_detail')
            ->where('id',$id)
            ->find();

        if (!$result){
            return $this->getJson(['msg'=>'数据不存在','code'=>201]);
        }

        if ($result['status'] != 3){
            return $this->getJson(['msg'=>'已处理','code'=>201]);
        }

        if ($result['data_type'] == 1){
            $result = Db::name('api_user')
                ->where('id',$result['user_id'])
                ->inc('price',(float)$result['price'])
                ->update();

            if (!$result){
                return $this->getJson(['msg'=>'金额退回失败','code'=>201]);
            }
        }else{
            $result = Db::name('api_user')
                ->where('id',$result['user_id'])
                ->inc('price',(float)$result['price'])
                ->update();

            if (!$result){
                return $this->getJson(['msg'=>'金额发放失败','code'=>201]);
            }
        }

        $result = Db::name('api_fund_detail')
            ->where('id',$id)
            ->update([
                'status' => 2
            ]);
        if (!$result){
            return $this->getJson(['msg'=>'处理失败','code'=>201]);
        }

        return $this->getJson(['msg'=>'成功','code'=>200]);
    }

    // 编辑
    public function edit($id){
        if (Request::isAjax()) {
            return $this->getJson(S::goEdit(Request::post(),$id));
        }
        return $this->fetch('',['model' => M::find($id)]);
    }

    // 状态
    public function status($id){
        return $this->getJson(S::goStatus(Request::post('status'),$id));
        }

    // 删除
    public function remove($id){
        return $this->getJson(S::goRemove($id));
        }

    // 批量删除
    public function batchRemove(){
        return $this->getJson(S::goBatchRemove(Request::post('ids')));
        }

    // 回收站
    public function recycle(){
        if (Request::isAjax()) {
            return $this->getJson(S::goRecycle());
        }
        return $this->fetch();
    }

}
