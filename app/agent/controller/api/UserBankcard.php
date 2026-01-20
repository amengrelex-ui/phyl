<?php
declare (strict_types=1);

namespace app\agent\controller\api;

use think\facade\Db;
use think\facade\Request;
use app\common\service\ApiAccount as S;
use app\common\model\ApiAccount as M;

class UserBankcard extends \app\agent\controller\Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    public function index()
    {
        if (Request::isAjax()) {
            return $this->getJson(M::getList());
        }

        return $this->fetch();
    }
    
    public function list(){
        if (Request::isAjax()) {
            $param = $this->request->param();
            $limit = $param['limit'];
            $list = Db::name('api_bank')->order('id', 'asc')->paginate($limit);
            return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
        }
        
        return $this->fetch();
    }
    
    public function bank_add(){
        if (Request::isAjax()) {
            $param = $this->request->param();
            $param['addtime'] = time();
            $ret = Db::name('api_bank')->insert($param);
            if($ret){
                return ['code'=>200,'msg'=>'添加成功'];
            } else {
                return ['code'=>201,'msg'=>'添加失败'];
            }
        }
        
        return $this->fetch();
    }
    
    public function bank_edit(){
        if (Request::isAjax()) {
            $param = $this->request->param();
            $param['addtime'] = time();
            $id = $param['id'];
            unset($param['id']);
            $ret = Db::name('api_bank')->where('id', $id)->update($param);
            if($ret){
                return ['code'=>200,'msg'=>'编辑成功'];
            } else {
                return ['code'=>201,'msg'=>'编辑失败'];
            }
        }
        $id = $this->request->param('id');
        $list = Db::name('api_bank')->where('id', $id)->find();
        $this->assign('list', $list);
        
        return $this->fetch();
    }
    
    // public function buy($id){
    //     if (Request::isAjax()) {
            
    //     }
    //     $user = Db::name('api_user')->where('id', $id)->find();
    //     $products = Db::name('api_product')->where('product_type', 2)->where('product_status', 1)->order('sort', 'desc')->select();
    //     return $this->fetch('', ['id' => $id, 'user' => $user, 'products' => $products]);
    // }

    public function user_data($id)
    {
        if (Request::isAjax()) {
            return $this->getJson(M::getList($id));
        }

        return $this->fetch('', ['id' => $id]);
    }
    
    
    
    
    

    public function fund_detail($id)
    {
        if (Request::isAjax()) {
            return $this->getJson(\app\common\model\ApiFundDetail::getList($id));
        }

        return $this->fetch('', ['id' => $id]);
    }
    
    

    // public function edit_password($id)
    // {
    //     if (Request::isAjax()) {
    //         $data = Request::post();

    //         $result = Db::name('api_user')
    //             ->where('id', $id)
    //             ->update(
    //                 [
    //                     'password' => md5(md5($data['password'])),
    //                 ]
    //             );
    //         if (!$result) {
    //             return $this->getJson(['msg' => '操作失败', 'code' => 201]);
    //         }

    //         return $this->getJson(['msg' => '操作成功', 'code' => 200]);
    //     }

    //     return $this->fetch();
    // }

    public function add(){
        if (Request::isAjax()){
            return $this->getJson(S::goAdd(Request::post()));
        }

        return $this->fetch('');
    }




    // 编辑信息
    public function edit($id){
        
        if (Request::isAjax()) {
            return $this->getJson(S::goEdit(Request::post(), $id));
        }

        $data = M::find($id);

        $temp = Db::name('api_account')
            ->where('id',$id)
            ->where('delete_time IS NULL')
            ->find();
        $userinfo = Db::name('api_user')->where('id',$temp['user_id'])->find();    
            
        if ($temp){
            $data['id'] = $temp['id'];
            $data['user_id'] = $temp['user_id'];
            $data['username'] = $userinfo['username'];
            $data['account_name'] = $temp['account_name'];
            $data['account_number'] = $temp['account_number'];
            $data['name_of_deposit_bank'] = $temp['name_of_deposit_bank'];
        }

        return $this->fetch('', ['model' => $data]);
    }





    // 状态
    public function status($id)
    {
        return $this->getJson(S::goStatus(Request::post('status'), $id));
    }





    // 删除
    public function remove($id)
    {
        return $this->getJson(S::goRemove($id));
    }





    // 批量删除
    public function batchRemove()
    {
        return $this->getJson(S::goBatchRemove(Request::post('ids')));
    }





    // 回收站
    public function recycle()
    {
        if (Request::isAjax()) {
            return $this->getJson(S::goRecycle());
        }

        return $this->fetch();
    }

}
