<?php

declare(strict_types=1);

namespace app\admin\controller\api;

use think\facade\Session;
use think\facade\Request;
use think\facade\Db;
use app\common\service\ApiFundDetail as S;
use app\common\model\ApiFundDetail as M;

class UserRecharge extends  \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    // 用户充值列表
    public function index()
    {
        if (Request::isAjax()) {
            return $this->getJson(M::getRechargeList());
        }
        return $this->fetch();
    }

    // 用户充值列表
    public function agent_index()
    {
        if (Request::isAjax()) {
            $admin = Db::name('admin_admin')->where('id', Session::get('admin.id'))->find();
            $agent_id = Db::name('api_user')->where('mobile', $admin['username'])->value('id');
            $where = [];
            $limit = input('get.limit');
            $where[] = ['agent_id', '=', $agent_id];
            if ($search = input('get.mobile')) {
                $user_id = Db::name('api_user')
                    ->where('mobile', 'like', "%" . $search . "%")
                    ->column('id');
                if (empty($user_id)) {
                    return ['code' => 0, 'data' => [], 'extend' => ['count' => 0, 'limit' => $limit]];
                }
                $where[] = ['user_id', 'in', $user_id];
            }

            $where[] = ['data_type', 'in', 2];

            if ($search = input('get.status')) {
                $where[] = ['d.status', 'in', $search];
            }


            //日期查询条件
            if ($range =  input('get.range')) {
                $dates = explode('~', $range);
                $where[] = ['d.create_time', '>', trim($dates[0]) . " 00:00:00"];
                $where[] = ['d.create_time', '<', trim($dates[1]) . " 23:59:59"]; // 没有空格？
            }


            $list = Db::name('api_fund_detail')->alias('d')->join('cloud_times_api_user u', 'd.user_id=u.id')->order('d.id', 'desc')->where($where)->paginate($limit)->each(function ($item) {
                $user = \think\facade\Db::name('api_user')
                    ->field('*')
                    ->where('id', $item['user_id'])
                    ->find();
                $item['mobile'] = !empty($user['mobile']) ? $user['mobile'] : 0;
                $recharge_type_txt = '充值';
                if ($item['recharge_type'] == 3) {
                    $recharge_type_txt = '积分兑换';
                } else if ($item['recharge_type'] == 2) {
                    $recharge_type_txt = '任务返现';
                }
                $item['recharge_type_txt'] = $recharge_type_txt;
                return $item;
            });

            return ['code' => 0, 'data' => $list->items(), 'count' => $list->total(), 'limit' => $limit];
        }
        return $this->fetch();
    }

    //通过提现申请
    public function data_success($id)
    {
        $info = Db::name('api_fund_detail')
            ->where('id', $id)
            ->find();
        if (!$info) {
            return $this->getJson(['msg' => '数据不存在', 'code' => 201]);
        }

        if ($info['status'] != 3) {
            return $this->getJson(['msg' => '已处理', 'code' => 201]);
        }

        if ($info['data_type'] == 2) {
            $userId = $info['user_id'];
            $price = (float)$info['price'];
            $orderNo = $info['order_no'];
            $config = config('web');
            // 查询用户
            // $user = Db::name('api_user')->where('id', $userId)->field('mobile,withdraw_price, parent_user_id')->find();
            // $withdrawPrice = (float)$user['withdraw_price'];
            // $outPrice = min($withdrawPrice, (float)($price* $config['recharge_rate']));
            // $outPrice = (float)($price* $config['recharge_rate']);
            // if ($withdrawPrice > 0) {
                // Db::name('api_fund_detail')->insert([
                //     'data_type'   => 28,
                //     'user_id'     => $userId,
                //     'price'       => $outPrice,
                //     'status'      => 1,
                //     'remarks'     => $user['mobile']."：充值，释放：{$outPrice}元|{$orderNo}",
                //     'create_time' => date('Y-m-d H:i:s',time()),
                // ]);
            // }
            $result = Db::name('api_user')
                ->where('id', $userId)
                // ->dec('withdraw_price',(float)$outPrice)
                // ->inc('price',(float)($price+$outPrice))
                ->inc('price',(float)($price))
                ->inc('grow_up', (int)$price)
                ->update();

            if (!$result) {
                return $this->getJson(['msg' => '金额发放失败', 'code' => 201]);
            }
            // 父级收益
            // $parentId = $user['parent_user_id'];
            // if ($parentId) {
            //     $parentWithdraw = (float) Db::name('api_user')->where('id', $parentId)->value('withdraw_price');
            //     $outParentPrice = min($parentWithdraw, (float)($price * $config['recharge_parent_rate']));
            //     if ($outParentPrice > 0) {
            //         Db::name('api_fund_detail')->insert([
            //             'data_type'   => 28,
            //             'user_id'     => $parentId,
            //             'price'       => $outParentPrice,
            //             'status'      => 1,
            //             'remarks'     => $user['mobile']."：充值，上级释放：{$outParentPrice}元|{$orderNo}",
            //             'create_time' => date('Y-m-d H:i:s',time()),
            //         ]);

            //         Db::name('api_user')
            //             ->where('id', $parentId)
            //             ->dec('withdraw_price', $outParentPrice)
            //             ->inc('cash_price', $outParentPrice)
            //             ->update();
            //     }
            // }
        }
        $result = Db::name('api_fund_detail')
            ->where('id', $id)
            ->update([
                'status' => 1,
                'remarks' => str_replace('：', '', $info['remarks'])
            ]);
        if (!$result) {
            return $this->getJson(['msg' => '处理失败', 'code' => 201]);
        }

        return $this->getJson(['msg' => '成功', 'code' => 200]);
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
        return $this->fetch('', ['model' => M::find($id)]);
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