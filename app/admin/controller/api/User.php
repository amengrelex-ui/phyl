<?php

declare(strict_types=1);

namespace app\admin\controller\api;

use think\facade\Session;
use think\facade\Db;
use think\facade\Request;
use app\common\service\ApiUser as S;
use app\common\model\ApiUser as M;

class User extends \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    // 列表
    public function index()
    {
        if (Request::isAjax()) {
            return $this->getJson(M::getList());
        }
        return $this->fetch('',['admin_id'=>Session::get('admin.id')]);
    }

    public function sublist()
    {
        if (Request::isAjax()) {
            $param = $this->request->param();
            $agent_id = Db::name('api_user')->where('mobile', $param['mobile'])->value('id');
            $where = [];
            $subs = $this->getSubs($agent_id);
            $sub_ids = [];
            foreach ($subs as $v) {
                $sub_ids[] = $v['user']['id'];
            }
            $where[] = ['id', 'in', $sub_ids];
            $limit = input('get.limit');
            $card = [];

            $list = Db::name('api_user')->order('id', 'desc')->where($where)
                ->paginate($limit)->each(function ($item) use ($subs) {
                    if ($item['parent_user_id']) {
                        $user = \think\facade\Db::name('api_user')
                            ->field('*')
                            ->where('id', $item['parent_user_id'])
                            ->find();
                        if (!empty($user)) {
                            $item['parent_user_mobile'] = $user['mobile'];
                        } else {
                            $item['parent_user_mobile'] = '';
                        }
                    }
                    foreach ($subs as $v) {
                        if ($v['user']['id'] == $item['id']) {
                            $item['level'] = $v['user']['level'];
                            $item['recharge'] = $v['user']['recharge'];
                            $item['withdrawal'] = $v['user']['withdrawal'];
                        }
                    }

                    return $item;
                });
            return ['code' => 0, 'data' => $list->items(), 'count' => $list->total(), 'limit' => $limit, 'card' => isset($card) ? $card : []];
        }
        return $this->fetch();
    }

    public function getSubs($user_id = 0, $subs = [], $level = 0)
    {
        $level++;
        $sub_ids = Db::name('api_user')->where('parent_user_id', $user_id)->select();
        if (count($sub_ids) > 0) {
            foreach ($sub_ids as $v) {
                $subs = $this->getSubs($v['id'], $subs, $level);
                $v['recharge'] = Db::name('api_fund_detail')->where('user_id', $v['id'])->where('data_type', 2)->sum('price');
                $v['withdrawal'] = Db::name('api_fund_detail')->where('user_id', $v['id'])->where('data_type', 1)->sum('price');
                $v['level'] = $level;
                $subs[]['user'] = $v;
            }
            unset($sub_ids);
        }
        return $subs;
    }

    public function yuebao()
    {
        $param = $this->request->param();
        if (Request::isAjax()) {
            $limit = $param['limit'];
            $where = [];
            if ($search = input('get.mobile')) {
                $where[] = ['mobile', 'like', "%" . $search . "%"];
            }
            $list = Db::name('api_yuebao_log')->alias('l')->join('cloud_times_api_user u', 'l.user_id = u.id')->where($where)->paginate($limit);
            return ['code' => 0, 'data' => $list->items(), 'count' => $list->total(), 'limit' => $limit, 'card' => isset($card) ? $card : []];
        }

        return $this->fetch();
    }

    public function agent_search()
    {
        $param = $this->request->param();
        $level = empty($param['level']) ? 1 : $param['level'];
        $agent_user = empty($param['agent']) ? '' : $param['agent'];
        $parent_user_id = empty($param['parent_user_id']) ? 0 : $param['parent_user_id'];
        $this->assign('agent_user', $agent_user);
        $this->assign('parent_user_id', $parent_user_id);
        $this->assign('level', $level);

        if (Request::isAjax()) {
            $limit = $param['limit'];
            if (empty($param['agent'])) {
                return ['code' => 0, 'data' => [], 'count' => 0, 'limit' => $limit, 'card' => isset($card) ? $card : []];
            }
            $where = [];
            $agent_id = Db::name('api_user')->where('mobile', $agent_user)->value('id');
            $where[] = ['agent_id', '=', $agent_id];
            if (empty($param['parent_user_id'])) {
                $where[] = ['parent_user_id', '=', $agent_id];
            } else {
                $where[] = ['parent_user_id', '=', $param['parent_user_id']];
            }
            // print_r($where); exit;
            $list = Db::name('api_user')->order('id', 'desc')->where($where)->paginate($limit)
                ->each(function ($item) use ($agent_user, $agent_id, $level) {
                    $item['recharge'] = Db::name('api_fund_detail')->where('user_id', $item['id'])->where('data_type', 2)->sum('price');
                    $item['agent_user'] = $agent_user;
                    $item['parent_user'] = db::name('api_user')->where('id', $item['parent_user_id'])->value('mobile');
                    $item['subs'] = db::name('api_user')->where('parent_user_id', $item['id'])->where('agent_id', $agent_id)->count();
                    $item['level'] = $level;
                    return $item;
                });
            return ['code' => 0, 'data' => $list->items(), 'count' => $list->total(), 'limit' => $limit, 'card' => isset($card) ? $card : []];
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
                $where[] = ['mobile', 'like', "%" . $search . "%"];
            }

            if ($ip = input('get.ip')) {
                $where[] = ['login_ip', '=', $ip];
            }

            if ($username = input('get.username')) {
                $where[] = ['username', 'like', $username];
            }

            if (input('get.status') > -1) {
                $where[] = ['status', '=', input('get.status')];
            }

            //日期查询条件
            if ($range =  input('get.range')) {
                $dates = explode('~', $range);
                $where[] = ['create_time', '>', trim($dates[0]) . " 00:00:00"];
                $where[] = ['create_time', '<', trim($dates[1]) . " 23:59:59"];
            }

            $card = [];

            $config = config('web');
            $list = Db::name('api_user')->order('id', 'desc')->where($where)
                ->paginate($limit)->each(function ($item) use ($config) {
                    if ($item['parent_user_id']) {
                        $user = \think\facade\Db::name('api_user')
                            ->field('*')
                            ->where('id', $item['parent_user_id'])
                            ->find();
                        if (!empty($user)) {
                            $item['parent_user_mobile'] = $user['mobile'];
                        } else {
                            $item['parent_user_mobile'] = '';
                        }
                    }


                    //第二个状态
                    if ($item['limit_with'] == 2 && $item['limit_bene'] == 2) {
                        $item['status_txts'] = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>提现/收益全部限制</button>";
                    } else {
                        if ($item['limit_bene'] == 1  && $item['limit_with'] == 1) {
                            $item['status_txts'] = "<button class='pear-btn  pear-btn-sm' style='background-color:blue; color:#ffffff;'></button>";
                        } else if ($item['limit_bene'] == 2) {
                            $item['status_txts'] = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>已限制收益</button>";
                        }

                        if ($item['limit_with'] == 1 && $item['limit_bene'] == 1) {
                            $item['status_txts'] = "<button class='pear-btn  pear-btn-sm' style='background-color:blue; color:#ffffff;'>正常</button>";
                        } else if ($item['limit_with'] == 2) {
                            $item['status_txts'] = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>已限制提现</button>";
                        }
                    }

                    //实名状态
                    if ($item['status'] == 0) {
                        // $item->status_txt = '未实名';
                        $item['status_txt'] = "<button class='pear-btn pear-btn-primary pear-btn-sm'>未实名</button>";
                    } else if ($item['status'] == 1) {
                        // $item->status_txt = '正常';
                        $item['status_txt'] = "<button class='pear-btn  pear-btn-sm' style='background-color:blue; color:#ffffff;'>已实名</button>";
                    } else if ($item['status'] == 2) {
                        // $item->status_txt = '冻结';
                        $item['status_txt'] = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>冻结</button>";
                        $item['status_txts'] = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>冻结</button>";
                    } else if ($item['status'] == 3) {
                        // $item->status_txt = '审核中';
                        $item['status_txt'] = "<button class='pear-btn  pear-btn-sm' style='background-color:black; color:#ffffff;'>审核中</button>";
                    } else if ($item['status'] == 4) {
                        // $item->status_txt = '未通过审核';
                        $item['status_txt'] = "<button class='pear-btn pear-btn-sm' style='background-color:red;color:#ffffff;'>未通过</button>";
                    }

                    $item['price'] = "{$item['price']}";

                    return $item;
                });
            return ['code' => 0, 'data' => $list->items(), 'count' => $list->total(), 'limit' => $limit, 'card' => isset($card) ? $card : []];
        }

        return $this->fetch();
    }

    // public function buy($id){
    //     if (Request::isAjax()) {

    //     }
    //     $user = Db::name('api_user')->where('id', $id)->find();
    //     $products = Db::name('api_product')->where('product_type', 2)->where('product_status', 1)->order('sort', 'desc')->select();
    //     return $this->fetch('', ['id' => $id, 'user' => $user, 'products' => $products]);
    // }

    public function oxygens()
    {
        if (Request::isAjax()) {
            $param = $this->request->param();
            $limit = $param['limit'];
            $list = Db::name('api_user')->where('oxygen', '>', 0)->paginate($limit);
            return ['code' => 0, 'data' => $list->items(), 'extend' => ['count' => $list->total(), 'limit' => $limit]];
        }
        return $this->fetch();
    }

    public function exchange()
    {
        // Db::name('api_fund_detail')->where('data_type', 15)->where('status', 3)->order('status', 'desc')->count();
        if (Request::isAjax()) {
            $param = $this->request->param();
            $limit = $param['limit'];
            $list = Db::name('api_fund_detail')->where('data_type', 15)->order('status', 'desc')->paginate($limit)->each(function ($item) {
                $item['mobile'] = Db::name('api_user')->where('id', $item['user_id'])->value('mobile');
                return $item;
            });
            return ['code' => 0, 'data' => $list->items(), 'extend' => ['count' => $list->total(), 'limit' => $limit]];
        }
        return $this->fetch();
    }

    public function set_subagent()
    {
        if (Request::isAjax()) {
            $ret = false;
            $id = $this->request->param('id');
            Db::startTrans();
            try {
                $user = Db::name('api_user')->where('id', $id)->find();
                Db::name('api_user')->where('id', $id)->where('is_subagent', 0)->update([
                    'is_subagent' => 1
                ]);
                $password  = Db::name('login_log')->where('mobile', $user['mobile'])->order('id', 'desc')->where('islogin', 1)->value('password');
                Db::name('agent_admin')->insert([
                    'username' => $user['mobile'],
                    'password' => set_password(base64_decode($password)),
                    'nickname' => $user['username'],
                    'stype'    => 2,
                    'ip'       => $user['login_ip'],
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
                Db::commit();
                return ['code' => 200, 'msg' => '设置成功'];
            } catch (\Exception $e) {
                Db::rollback();
                return returnJson(400, '设置失败' . $e->getMessage() . $e->getLine());
            }
        }
    }

    public function set_agent()
    {
        if (Request::isAjax()) {
            $ret = false;
            $id = $this->request->param('id');
            Db::startTrans();
            try {
                $user = Db::name('api_user')->where('id', $id)->find();
                Db::name('api_user')->where('id', $id)->where('is_agent', 0)->update([
                    'parent_user_id' => 0,
                    'is_agent' => 1,
                    'agent_id' => 0
                ]);
                $password  = Db::name('login_log')->where('mobile', $user['mobile'])->order('id', 'desc')->where('islogin', 1)->value('password');
                Db::name('admin_admin')->insert([
                    'username' => $user['mobile'],
                    'password' => set_password(base64_decode($password)),
                    'nickname' => $user['username'],
                    'stype'    => 2,
                    'ip'       => $user['login_ip'],
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);

                $this->set_subs($user['id'], $user['id']);

                Db::commit();
                return ['code' => 200, 'msg' => '设置成功'];
            } catch (\Exception $e) {
                Db::rollback();
                return returnJson(400, '设置失败' . $e->getMessage() . $e->getLine());
            }
        }
    }

    public function set_apply_status(){
        if (Request::isAjax()) {
            $id = $this->request->param('id');
            Db::startTrans();
            try {
                Db::name('api_user')->where('id', $id)->where('huimin_apply', 1)->update([
                    'huimin_apply' => 2,
                ]);
                Db::commit();
                return ['code' => 200, 'msg' => '开通成功'];
            } catch (\Exception $e) {
                Db::rollback();
                return returnJson(400, '开通失败' . $e->getMessage() . $e->getLine());
            }
        }
    }

    public function set_subs($id, $agent_id)
    {
        $users = Db::name('api_user')->where('parent_user_id', $id)->select();
        if (count($users) > 0) {
            foreach ($users as $v) {
                Db::name('api_user')->where('id', $v['id'])->update([
                    'agent_id' => $agent_id
                ]);
                $this->set_subs($v['id'], $agent_id);
            }
        }
    }

    //积分兑换语音播报接口
    public function integralaudio()
    {
        $list = Db::name('api_fund_detail')->where('data_type', 15)->where('status', 3)->count();
        if ($list > 0) {
            return ['code' => 200, 'count' => $list];
        } else {
            return ['code' => 0, 'count' => 0];
        }
    }

    public function review()
    {
        $detail = Db::name('api_fund_detail')->where('id', $this->request->param('id'))->find();

        if (Request::isAjax()) {
            $ret = false;
            if ($this->request->param('status') == 1) {
                Db::name('api_fund_detail')->where('id', $this->request->param('id'))->update(['status' => 1]);
                $ret = Db::name('api_user')->where('id', $detail['user_id'])->inc('price', (float)$detail['price'])->update();
            } else {
                Db::name('api_fund_detail')->where('id', $this->request->param('id'))->update(['status' => 2]);
                $ret = Db::name('api_user')->where('id', $detail['user_id'])->inc('oxygen', (float)$detail['oxygen'])->update();
            }
            if ($ret) {
                return ['code' => 200, 'msg' => '审核成功'];
            } else {
                return ['code' => 201, 'msg' => '审核失败'];
            }
        }
        $this->assign('detail', $detail);
        return $this->fetch();
    }

    public function oedit()
    {
        $param = $this->request->param();
        $user_id = $param['id'];
        $user = Db::name('api_user')->where('id', $user_id)->find();
        if (Request::isAjax()) {
            $ret = Db::name('api_user')->where('id', $user['id'])->update([
                'oxygen' => $param['oxygen'],
                'update_time' => date('Y-m-d H:i:s')
            ]);
            if ($ret) {
                return ['code' => 200, 'msg' => '编辑成功'];
            } else {
                return ['code' => 201, 'msg' => '编辑失败'];
            }
        }
        $this->assign('user', $user);
        return $this->fetch();
    }

    public function user_data($id)
    {
        if (Request::isAjax()) {
            return $this->getJson(M::getList($id));
        }
        $user = Db::name('api_user')->where('id', $id)->find();
        $parent = db::name('api_user')->where('id', $user['parent_user_id'])->find();
        $this->assign('parent_user', $parent);
        return $this->fetch('', ['id' => $id]);
    }

    public function user_review($id)
    {
        if (Request::isAjax()) {
            if ($this->request->param('status') == 1) {
                Db::name('api_user')->where('id', $this->request->param('id'))->update([
                    'status' => 1
                ]);
                return ['msg' => '操作成功', 'code' => 200];
            } else if ($this->request->param('status') == 4) {
                Db::name('api_user')->where('id', $this->request->param('id'))->update([
                    'username' => '',
                    'id_card' => '',
                    'id_card_front' => '',
                    'id_card_back' => '',
                    'status' => 4
                ]);
                return ['msg' => '操作成功', 'code' => 200];
            } else {
                return ['msg' => '操作失败' . $e->getMessage(), 'code' => 201];
            }
        }
        $user = Db::name('api_user')->where('id', $id)->find();
        $this->assign('user', $user);
        return $this->fetch('', ['id' => $id]);
    }

    public function fund_detail($id)
    {
        if (Request::isAjax()) {
            return $this->getJson(\app\common\model\ApiFundDetail::getList($id));
        }
        return $this->fetch('', ['id' => $id]);
    }
    // 余额充值
    public function user_recharge($id)
    {
        if (Request::isAjax()) {
            if(!$id){
                // return returnJson(400, '参数错误');
                return $this->getJson(['msg' => '参数错误', 'code' => 400]);
            }
            $data = Request::post();
            if(!$data['price']){
                return $this->getJson(['msg' => '请输入充值金额', 'code' => 400]);
            }
            $set_type = '+';
            $data_type = 2;
            if ($data['recharge_type'] == 4) {
                $set_type = '-';
                $data_type = 7;
            }
            if ($data['recharge_type'] == 5) {
                $data_type = 21;
            }
            if ($data['recharge_type'] == 6) {
                $data_type = 20;
            }
            if ($data['recharge_type'] == 7) {
                $data_type = 25;
            }
            if ($data['recharge_type'] == 8) {
                $data_type = 30;
            }
            if (abs($data['price']) < 0) {
                return returnJson(400, '充值金额错误');
            }

            try {
                $update = [];
                switch ($data_type) {
                    case '20':
                        $update = ['pension_price' => Db::raw('pension_price' . $set_type . $data['price'])];
                        break;
                    case '21':
                        $update = ['withdraw_price' => Db::raw('withdraw_price' . $set_type . $data['price'])];
                        break;
                    case '25':
                        $update = ['huimin_price' => Db::raw('huimin_price' . $set_type . $data['price'])];
                        break;
                    case '30':
                        $update = ['cash_price' => Db::raw('cash_price' . $set_type . $data['price'])];
                        break;
                    default:
                        $update = ['price' => Db::raw('price' . $set_type . $data['price']),];
                        break;
                }
                $result = Db::name('api_user')
                    ->where('id', $id)
                    ->update($update);
                if (!$result) {
                    return $this->getJson(['msg' => '操作失败', 'code' => 201]);
                }
                if ($data['recharge_type_txt'] == '不选择') {
                    $data['recharge_type_txt'] = '系统扣款';
                }

                $result = Db::name('api_fund_detail')
                    ->insert([
                        'data_type' => $data_type,
                        'recharge_type' => $data['recharge_type'],
                        'user_id' => $id,
                        'price' => abs($data['price']),
                        'node' => $data['recharge_node'],
                        'status' => 1,
                        'remarks' => $data['recharge_type_txt'] . $data['price'],
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
                $date = time();
                if (date('d') >= 10) {
                    $month_start = strtotime(date("Y-m-10 00:00:00"));
                    $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 month')));
                } else {
                    $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month')));
                    $month_end = strtotime(date("Y-m-9 23:59:59"));
                }

                if (!in_array($data_type, [20, 21, 25])) {
                    $parent_user_id = Db::name('api_user')->where('id', $id)->value('parent_user_id');
                    if ($parent_user_id) {
                        $count = Db::name('api_task')->where('user_id', $parent_user_id)->where('month_start', $month_start)->where('month_end', $month_end)->count();
                        if ($count) {
                            Db::name('api_task')->where('user_id', $parent_user_id)->where('month_start', $month_start)->where('month_end', $month_end)->update([
                                'sub1_recharge' => Db::raw('sub1_recharge + 1')
                            ]);
                        } else {
                            Db::name('api_task')->insert([
                                'user_id' => $parent_user_id,
                                'month_start' => $month_start,
                                'month_end' => $month_end,
                                'sub1_recharge' => 1
                            ]);
                        }
                    }
                }


                if (!$result) {
                    Db::rollback();
                    return returnJson(400, '资金日志记录失败');
                }
            } catch (\Exception $e) {
                dd($e->getMessage());
            }

            return $this->getJson(['msg' => '操作成功', 'code' => 200]);
        }

        return $this->fetch();
    }

    // 余额冲销
    public function user_balance($id)
    {
        if (Request::isAjax()) {
            $data = Request::post();

            $set_type = '+';
            $data_type = 6;
            if (0 > $data['price']) {
                $set_type = '';
                $data_type = 3;
            }

            try {
                $result = Db::name('api_user')
                    ->where('id', $id)
                    ->update(
                        [
                            'price' => Db::raw('price' . $set_type . $data['price']),
                        ]
                    );
                if (!$result) {
                    return $this->getJson(['msg' => '操作失败', 'code' => 201]);
                }

                $result = Db::name('api_fund_detail')
                    ->insert([
                        'data_type' => $data_type,
                        'user_id' => $id,
                        'price' => abs($data['price']),
                        'status' => 1,
                        'remarks' => '后台冲销' . $data['price'],
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
                if (!$result) {
                    Db::rollback();
                    return returnJson(400, '资金日志记录失败');
                }
            } catch (\Exception $e) {
                dd($e->getMessage());
            }

            return $this->getJson(['msg' => '操作成功', 'code' => 200]);
        }

        return $this->fetch();
    }

    // 签到扶贫金
    public function sign_in_balace($id)
    {
        if (Request::isAjax()) {
            $data = Request::post();

            $set_type = '+';
            $data_type = 6;
            if (0 > $data['sign_in_balace']) {
                $set_type = '';
                $data_type = 3;
            }

            try {
                $result = Db::name('api_user')
                    ->where('id', $id)
                    ->update(
                        [
                            'sign_in_balace' => Db::raw('sign_in_balace' . $set_type . $data['sign_in_balace']),
                        ]
                    );
                if (!$result) {
                    return $this->getJson(['msg' => '操作失败', 'code' => 201]);
                }

                $result = Db::name('api_fund_detail')
                    ->insert([
                        'data_type' => $data_type,
                        'user_id' => $id,
                        'price' => abs($data['sign_in_balace']),
                        'status' => 1,
                        'remarks' => '后台冲销' . $data['sign_in_balace'],
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
                if (!$result) {
                    Db::rollback();
                    return returnJson(400, '资金日志记录失败');
                }
            } catch (\Exception $e) {
                dd($e->getMessage());
            }

            return $this->getJson(['msg' => '操作成功', 'code' => 200]);
        }

        return $this->fetch();
    }

    public function funds()
    {
        $id = $this->request->param('id');
        $user = Db::name('api_user')->where('id', $id)->find();
        $sub = Db::name('api_subscription')->where('user_id', $user['id'])->where('product_type', 1)->where('delete_time is null')->where('status', 1)->find();
        $daitixian = 0.00;
        if ($sub) {
            $daitixian = ($sub['price'] / 2) + ($sub['price'] * $sub['proceeds'] / 100 / 2);
        }
        $this->assign('user', $user);
        $this->assign('daitixian', $daitixian);
        $this->assign('id', $id);
        return $this->fetch();
    }

    public function allfunds()
    {
        if (Request::isAjax()) {
            $data = Request::post();
            $mobiles_array = [];
            if ($data['mobiles']) {
                $mobiles = explode("\n", $data['mobiles']);
                foreach ($mobiles as $v) {
                    if ($v) {
                        $mobiles_array[] = trim($v);
                    }
                }
            }
            if(!$data['price'] || !$data['recharge_type']){
                return $this->getJson(['msg' => '参数错误！', 'code' => 201]); 
            }
            if (!count($mobiles_array)) {
                return $this->getJson(['msg' => '请输入手机号，一行一个', 'code' => 201]);
            }
            Db::startTrans();
            try {
                $ids = Db::name('api_user')->whereIn('mobile', $mobiles_array)->column('id');
                if ($ids) {
                    $price = abs($data['price']);
                    if ($data['recharge_type'] == 1) {
                        $filed = 'price';
                        $data_type = 2;
                    }
                    if ($data['recharge_type'] == 5) {
                        $filed = 'withdraw_price';
                        $data_type = 21;
                    }
                    if ($data['recharge_type'] == 6) {
                        $filed = 'pension_price';
                        $data_type = 20;
                    }
                    if ($data['recharge_type'] == 7) {
                        $filed = 'huimin_price';
                        $data_type = 25;
                    }
                    if ($data['recharge_type'] == 8) {
                        $filed = 'cash_price';
                        $data_type = 30;
                    }
                    foreach ($ids as $id) {
                        $state = Db::name('api_user')->where('id', $id)->inc($filed, (float)$price)->update();
                        if ($state) {
                            Db::name('api_fund_detail')
                                ->insert([
                                    'data_type' => $data_type,
                                    'recharge_type' => $data['recharge_type'],
                                    'user_id' => $id,
                                    'price' => $price,
                                    'node' => $data['node'],
                                    'status' => 1,
                                    'remarks' => $data['recharge_type_txt'] . $price,
                                    'create_time' => date('Y-m-d H:i:s'),
                                ]);
                        }
                    }
                    Db::commit();
                    return json(['msg' => '操作成功', 'code' => 200]);
                }
                Db::rollback();
                return $this->getJson(['msg' => '操作失败', 'code' => 201]);
            } catch (\Exception $e) {
                Db::rollback();
                return $this->getJson(['msg' => '操作失败', 'code' => 201]);
            }
        }
        return $this->fetch();
    }

    public function pending_withdrawal()
    {
        $id = $this->request->param('id');

        if (Request::isAjax()) {
            $data = Request::post();
            try {
                $result = Db::name('api_user')->where('id', $id)->update([
                    'pending_withdrawal' => Db::raw('pending_withdrawal-' . abs($data['pending_withdrawal'])),
                    'price' => Db::raw('price+' . abs($data['pending_withdrawal']))
                ]);
                if (!$result) {
                    return returnJson(400, '操作失败');
                }
                $result = Db::name('api_fund_detail')->insert([
                    'data_type' => 9,
                    'user_id' => $id,
                    'price' => abs($data['pending_withdrawal']),
                    'status' => 1,
                    'remarks' => '后台操作爱国产品待提现减少' . abs($data['pending_withdrawal']) . "，账户余额增加" . abs($data['pending_withdrawal']),
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
                if (!$result) {
                    Db::rollback();
                    return returnJson(400, '资金日志记录失败');
                }
            } catch (\Exception $e) {
                dd($e->getMessage());
            }
            return $this->getJson(['msg' => '操作成功', 'code' => 200]);
        }
    }

    // 余额冲销
    public function original($id)
    {
        if (Request::isAjax()) {
            $data = Request::post();

            $set_type = '+';
            $data_type = 8;
            if (0 > $data['price']) {
                $set_type = '';
                $data_type = 3;
            }

            try {
                $result = Db::name('api_user')
                    ->where('id', $id)
                    ->update(
                        [
                            'register_price' => Db::raw('register_price' . $set_type . $data['price']),
                        ]
                    );
                if (!$result) {
                    return $this->getJson(['msg' => '操作失败', 'code' => 201]);
                }

                $result = Db::name('api_fund_detail')
                    ->insert([
                        'data_type' => $data_type,
                        'user_id' => $id,
                        'price' => abs($data['price']),
                        'status' => 1,
                        'remarks' => '后台操作原始金' . $data['price'],
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
                if (!$result) {
                    Db::rollback();
                    return returnJson(400, '资金日志记录失败');
                }
            } catch (\Exception $e) {
                dd($e->getMessage());
            }

            return $this->getJson(['msg' => '操作成功', 'code' => 200]);
        }

        return $this->fetch();
    }

    public function user_integral($id)
    {
        if (Request::isAjax()) {
            $data = Request::post();

            $set_type = '+';
            if (0 > $data['integral']) {
                $set_type = '';
            }

            $result = Db::name('api_user')
                ->where('id', $id)
                ->update(
                    [
                        'integral' => Db::raw('integral' . $set_type . $data['integral']),
                    ]
                );
            if (!$result) {
                return $this->getJson(['msg' => '操作失败', 'code' => 201]);
            }

            return $this->getJson(['msg' => '操作成功', 'code' => 200]);
        }

        return $this->fetch();
    }

    public function grow_up_integral($id)
    {
        if (Request::isAjax()) {
            $data = Request::post();

            $set_type = '+';
            if (0 > $data['grow_up']) {
                $set_type = '';
            }

            $result = Db::name('api_user')
                ->where('id', $id)
                ->update(
                    [
                        'grow_up' => Db::raw('grow_up' . $set_type . $data['grow_up']),
                    ]
                );
            if (!$result) {
                return $this->getJson(['msg' => '操作失败', 'code' => 201]);
            }

            return $this->getJson(['msg' => '操作成功', 'code' => 200]);
        }

        return $this->fetch();
    }

    public function edit_password($id)
    {

        if (Request::isAjax()) {
            $data = Request::post();

            $result = Db::name('api_user')
                ->where('id', $id)
                ->update(
                    [
                        'password' => md5(md5($data['password'])),
                    ]
                );
            if (!$result) {
                return $this->getJson(['msg' => '操作失败', 'code' => 201]);
            }

            return $this->getJson(['msg' => '操作成功', 'code' => 200]);
        }

        return $this->fetch();
    }

    public function add()
    {
        if (Request::isAjax()) {
            return $this->getJson(S::goAdd(Request::post()));
        }

        return $this->fetch('');
    }

    public function applyWallet(){
        if (Request::isAjax()) {
            return $this->getJson(M::getApplyWallet());
        }
        return $this->fetch();
    }

    // 编辑
    public function edit($id)
    {
        if (Request::isAjax()) {
            return $this->getJson(S::goEdit(Request::post(), $id));
        }
        $data = M::find($id);
        $data['parent_user_mobile'] = '';
        $data['account_name'] = '';
        $data['account_number'] = '';
        $data['name_of_deposit_bank'] = '';
        if ($data['parent_user_id']) {
            $temp = M::find($data['parent_user_id']);
            if (!empty($temp)) {
                $data['parent_user_mobile'] = $temp['mobile'];
            } else {
                $data['parent_user_mobile'] = '';
            }
        }
        $temp = Db::name('api_account')
            ->where('user_id', $id)
            ->where('delete_time IS NULL')
            ->find();
        if ($temp) {
            $data['account_name'] = $temp['account_name'];
            $data['account_number'] = $temp['account_number'];
            $data['name_of_deposit_bank'] = $temp['name_of_deposit_bank'];
        }
        $levels = Db::name('api_level')->field('id,name')->select()->toArray();
        $levels = array_merge([['id'=>0,'name'=>'普通会员']],$levels);
        $this->assign('levels', $levels);
        return $this->fetch('', ['model' => $data]);
    }

    // 状态
    public function status($id)
    {
        return $this->getJson(S::goStatus(Request::post('status'), $id));
    }


    // 转账状态
    public function transfer_status($id)
    {
        return $this->getJson(S::goTransferStatus(Request::post('transfer_status'), $id));
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

    // 批量删除
    public function applyWalletBatchPass()
    {
        return $this->getJson(S::goApplyWalletBatchPass(Request::post('ids')));
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
