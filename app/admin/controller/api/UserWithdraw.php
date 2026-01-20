<?php

declare(strict_types=1);

namespace app\admin\controller\api;

use think\facade\Session;
use think\facade\Request;
use think\facade\Db;
use app\common\service\ApiFundDetail as S;
use app\common\model\ApiFundDetail as M;

class UserWithdraw extends  \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    // 用户提现列表
    public function index()
    {
        if (Request::isAjax()) {
            return $this->getJson(M::getWithdrawList());
        }
        return $this->fetch();
    }

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


            //日期查询条件
            if ($range =  input('get.range')) {
                $dates = explode('~', $range);
                $where[] = ['d.create_time', '>', trim($dates[0]) . " 00:00:00"];
                $where[] = ['d.create_time', '<', trim($dates[1]) . " 23:59:59"];
            }

            $where[] = ['data_type', 'in', 1];

            if ($search = input('get.status')) {
                $where[] = ['d.status', 'in', $search];
            }

            $list = Db::name('api_fund_detail')->order('d.id', 'desc')->alias('d')->join('cloud_times_api_user u', 'd.user_id=u.id')->where($where)->paginate($limit)->each(function ($item) {
                $user = \think\facade\Db::name('api_user')
                    ->alias('a')
                    ->field('a.*,p.account_name,p.account_number,p.name_of_deposit_bank')
                    ->join('api_account p', 'a.id = p.user_id')
                    ->where('a.id', $item['user_id'])
                    ->find();
                $item['mobile'] = !empty($user['mobile']) ? $user['mobile'] : 0;
                $item['account_name'] = $user['account_name'];
                $item['account_number'] = $user['account_number'];
                $item['name_of_deposit_bank'] = $user['name_of_deposit_bank'];
                return $item;
            });
            return ['code' => 0, 'data' => $list->items(), 'count' => $list->total(), 'limit' => $limit];
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

    // 拒绝提现申请
    public function refuse()
    {
        $id = $this->request->param('id');
        if ($this->request->isAjax()) {
            $reason = $this->request->param('reason');
            $result = Db::name('api_fund_detail')
                ->where('id', $id)
                ->find();
            if (!$result) {
                return $this->getJson(['msg' => '数据不存在', 'code' => 201]);
            }
            if ($result['status'] != 3 && $result['status'] != 5){
                return $this->getJson(['msg' => '已处理', 'code' => 201]);
            }
            Db::startTrans();
            try {
                $result = Db::name('api_user')
                    ->where('id', $result['user_id'])
                    ->inc('cash_price', (float)$result['price'])
                    ->update();
                if (!$result) {
                    return $this->getJson(['msg' => '金额退回失败', 'code' => 201]);
                }

                $result = Db::name('api_fund_detail')
                    ->where('id', $id)
                    ->update([
                        'status' => 2,
                        'reason' => $reason
                    ]);
                if (!$result) {
                    return $this->getJson(['msg' => '处理失败', 'code' => 201]);
                }
                Db::commit();
                return json(['msg' => '处理成功', 'code' => 200]);
            } catch (\Exception $e) {
                Db::rollback();
                echo "执行失败" . $e->getMessage() . $e->getLine();
                return $this->getJson(['msg' => $e->getMessage() . $e->getLine(), 'code' => 201]);
            }
        }

        $list = Db::name('api_fund_detail')->where('id', $id)->find();
        $user = Db::name('api_user')->where('id', $list['user_id'])->find();
        $this->assign('list', $list);
        $this->assign('user', $user);
        return $this->fetch();
    }

    //通过提现申请
    public function data_success($id)
    {
        $result = Db::name('api_fund_detail')
            ->where('id',$id)
            ->find();
        $info  = $result;
        if (!$result){
            return $this->getJson(['msg'=>'数据不存在','code'=>201]);
        }
        if ($result['status'] != 3 && $result['status'] != 5){
            return $this->getJson(['msg'=>'已处理','code'=>201]);
        }
        if ($result['data_type'] == 2){
            $result = Db::name('api_user')
                ->where('id',$result['user_id'])
                ->inc('cash_price',(float)$result['price'])
                ->inc('grow_up',(int)$result['price'])
                ->update();

            if (!$result){
                return $this->getJson(['msg'=>'金额发放失败','code'=>201]);
            }
        }
        $result = Db::name('api_fund_detail')
            ->where('id',$id)
            ->update([
                'status' => 1,
                'reason' =>'手动下发',
            ]);
        if (!$result){
            return $this->getJson(['msg'=>'处理失败','code'=>201]);
        }
        return $this->getJson(['msg'=>'成功','code'=>200]);
    }

    //通过提现申请
    public function xiafa_success($id)
    {
        $result = Db::name('api_fund_detail')
            ->where('id', $id)
            ->find();
        if (!$result) {
            return $this->getJson(['msg' => '数据不存在', 'code' => 201]);
        }
        $info = $result;
        if ($result['status'] != 3) {
            return $this->getJson(['msg' => '已处理', 'code' => 201]);
        }
        if ($result['data_type'] == 2) {
            $result = Db::name('api_user')
                ->where('id', $result['user_id'])
                ->inc('cash_price', (float)$result['price'])
                ->inc('grow_up', (int)$result['price'])
                ->update();
            if (!$result) {
                return $this->getJson(['msg' => '金额发放失败', 'code' => 201]);
            }
        }
        Db::startTrans();
        try {
            if (is_numeric($info['type'])) {
                $account = \think\facade\Db::name('api_account')
                    ->where('user_id', $info['user_id'])
                    ->where('type', $info['type'])
                    ->where('delete_time IS NULL')
                    ->find();
                if (!$account) {
                    throw new \think\Exception('该用户没有绑定' . ($info['type'] ? '支付宝' : '银行卡') . '收款', 10006);
                }
                $ctype = $info['type'] ? 1 : 0;
                $keys = 'PlzaM1PU6Bm1l0pjUcZSRFdt1ZtvciQrLv6zMoOfa2hWmvutZwtIdFQ3AP6c8PRd';
                if ($account) {
                    $data = [
                        'mchid' => '5867',
                        'out_trade_no' => $info['order_no'],
                        'money' => $info['price'],
                        'notifyurl' => 'https://' . $_SERVER['HTTP_HOST'] . '/api.php/notify/dfnotify',
                        'bankname' => !$ctype ? $account['name_of_deposit_bank'] : '支付宝',
                        'subbranch' => !$ctype ?  $account['name_of_deposit_bank'] : '支付宝',
                        'accountname' => $account['account_name'],
                        'cardnumber' => $account['account_number'],
                    ];
                    ksort($data);
                    $str = '';
                    foreach ($data as $key => $value) {
                        if ($value) $str .= $key . '=' . $value . '&';
                    }
                    $data['sign'] =  strtoupper(md5($str . 'key=' . $keys));
                    $res = $this->https_request('https://shapi.worldp55998.top/v1/dfapi/add', $data);
                    if ($res) {
                        $jsonData = json_decode($res, true);
                        if ($jsonData['status'] == 'success'){
                            Db::name('api_fund_detail')
                            ->where('id', $id)
                            ->update([
                                'status' => 4
                            ]);
                            Db::commit();
                            if($jsonData['transaction_id']){
                                return json(['msg' => '提交成功，等待打款中', 'code' => 200]);
                            }
                        }
                        throw new \think\Exception($jsonData['msg'], 202);
                    }
                }
            }
            Db::commit();
            return $this->getJson(['msg' => '下发成功', 'code' => 200]);
        } catch (\Exception $e) {
            Db::rollback();
            // return $this->getJson(['msg' => '下发异常，提交失败！', 'code' => 201]);
            return $this->getJson(['msg' => $e->getMessage(), 'code' => 201]);
        }
    }

    //批量审核通过
    public function batchPassed()
    {
        $ids = Request::post('ids');
        Db::startTrans();
        try {
            foreach ($ids as  $key => $value) {
                $result = Db::name('api_fund_detail')->where('id', $value)->find();
                if (!$result) {
                    continue;
                }

                if ($result['data_type'] == 1 && $result['status'] == 3) {
                    $result = Db::name('api_fund_detail')->where('id', $value)->update(['status' => 1]);
                } else {
                    continue;
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
        return $this->getJson(['msg' => '成功', 'code' => 200]);
    }

    public function https_request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    //批量拒绝
    public function batchRefuse()
    {
        $ids = $this->request->param('ids');
        $this->assign('ids', $ids);
        if ($this->request->isAjax()) {
            $reason = $this->request->param('reason');
            $ids = explode(',', $ids);
            Db::startTrans();
            try {
                foreach ($ids as $value) {
                    $result = Db::name('api_fund_detail')
                        ->where('id', $value)
                        ->find();
                    if ($result['data_type'] == 1 && $result['status'] == 3) {

                        $result = Db::name('api_user')
                            ->where('id', $result['user_id'])
                            ->inc('cash_price', (float)$result['price'])
                            ->update();
                        $result = Db::name('api_fund_detail')
                            ->where('id', $value)
                            ->update([
                                'status' => 2,
                                'reason' => $reason
                            ]);
                    } else {
                        continue;
                    }
                }
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
            }
            return $this->getJson(['msg' => '成功', 'code' => 200]);
        }
        return $this->fetch();
    }
}
