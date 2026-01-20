<?php

declare(strict_types=1);

namespace app\admin\controller\api;

use think\facade\Request;
use think\facade\Db;

class UserApplyBank extends  \app\admin\controller\Base
{

    protected $middleware = ['AdminCheck', 'AdminPermission'];
    // 列表
    public function index()
    {
        if (Request::isAjax()) {
            $where = [];
            $limit = input('get.limit');
            if ($search = input('get.mobile')) {
                $where[] = ['u.mobile', '=', $search];
            }
            if ($search = input('get.status')) {
                $where[] = ['r.bank_status', '=', $search];
            }
            // $where[] = ['r.is_delete', '=', 0];
            $list = Db::name('api_user_apply_bank')->alias('r')->leftJoin('api_user u', 'u.id = r.user_id')->order('r.bank_status', 'asc')->where($where)->field('u.mobile,u.username,r.*')->paginate($limit);
            $list =  ['code' => 0, 'data' => $list->items(), 'extend' => ['count' => $list->total(), 'limit' => $limit]];
            return $this->getJson($list);
        }
        return $this->fetch();
    }

    // 编辑
    public function edit($id)
    {
        $model =  Db::name('api_user_apply_bank')->alias('r')->leftJoin('api_user u', 'u.id = r.user_id')->where('r.id', $id)->field('u.mobile,r.*')->find();
        if (Request::isAjax()) {
            $data = Request::post();
            $data['id'] = $id;
            $data['update_time'] = date('Y-m-d H:i:s');

            $model = Db::name('api_user_apply_bank')->where('id', $id)->find();
            if (!$model) {
                return $this->getJson(['msg' => '记录不存在!', 'code' => 404]);
            }
            if ($data['bank_status'] == 3) {
                if (empty($data['node'])) {
                    return $this->getJson(['msg' => '请输入拒绝理由!', 'code' => 201]);
                }
                if ($model['bank_status'] == 3) {
                    return $this->getJson(['msg' => '请勿重复拒绝!', 'code' => 201]);
                }
                Db::startTrans();
                try {
                    Db::name('api_user')
                        ->where('id', $model['user_id'])
                        ->inc('price', (float)$model['bank_price'])
                        ->update();
                    Db::name('api_fund_detail')
                        ->where('user_id',$model['user_id'])
                        ->whereLike('remarks', '%银行卡->' . $id)
                        ->update(['node' => $data['node']]);
                    unset($data['node']);
                    $data['is_delete'] = 1;
                    Db::name('api_user_apply_bank')->update($data);
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    return $this->getJson(['msg' => '操作失败: ' . $e->getMessage(), 'code' => 500]);
                }
            } else {
                unset($data['node']);
                Db::name('api_user_apply_bank')->update($data);
            }

            return $this->getJson(['msg' => '处理成功', 'code' => 200]);
        }
        return $this->fetch('', ['model' => $model]);
    }
}
