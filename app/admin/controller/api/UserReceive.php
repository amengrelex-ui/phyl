<?php

declare(strict_types=1);

namespace app\admin\controller\api;

use think\facade\Request;
use app\common\service\ApiUserReceive as S;
use app\common\model\ApiUserReceive as M;

class UserReceive extends  \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    // 列表
    public function index()
    {
        if (Request::isAjax()) {
            return $this->getJson(M::getList());
        }
        return $this->fetch();
    }

    // 添加
    public function add()
    {
        if (Request::isAjax()) {
            return $this->getJson(S::goAdd(Request::post()));
        }
        return $this->fetch();
    }

    // 编辑
    public function edit($id)
    {
        if (Request::isAjax()) {
            return $this->getJson(S::goEdit(Request::post(), $id));
        }
        $model =  M::alias('r')->leftJoin('api_user u','u.id = r.user_id')->where('r.id',$id)->field('u.mobile,r.*')->find();
        return $this->fetch('', ['model' => $model]);
    }

    // 状态
    public function status($id)
    {
        return $this->getJson(S::goStatus(Request::post('status'), $id));
    }

    // 状态
    public function certify_audit($id)
    {
        return $this->getJson(S::goCertifyAuditStatus(Request::post('certify_audit'), $id));
    }

    // 状态
    public function income_audit($id)
    {
        return $this->getJson(S::goIncomeAuditStatus(Request::post('income_audit'), $id));
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
