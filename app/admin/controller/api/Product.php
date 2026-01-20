<?php
declare (strict_types = 1);

namespace app\admin\controller\api;

use think\facade\Request;
use app\common\service\ApiProduct as S;
use app\common\model\ApiProduct as M;
use think\facade\Db;

class Product extends  \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];

    // 列表
    public function index(){
        if (Request::isAjax()) {
            return $this->getJson(M::getList());
        }
        return $this->fetch();
    }

    // 添加
    public function add(){
        if (Request::isAjax()) {
            return $this->getJson(S::goAdd(Request::post()));
        }
        return $this->fetch('',['vouchers'=>Db::name('api_voucher')->field('id,name')->whereLike('name','%资格券%')->select()]);
    }

    // 编辑
    public function edit($id){
        if (Request::isAjax()) {
            return $this->getJson(S::goEdit(Request::post(),$id));
        }
        return $this->fetch('',['model' => M::find($id),'vouchers'=>Db::name('api_voucher')->field('id,name')->whereLike('name','%资格券%')->select()]);
    }

    // 状态
    public function status($id){
        return $this->getJson(S::goStatus(Request::post('product_status'),$id));
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
