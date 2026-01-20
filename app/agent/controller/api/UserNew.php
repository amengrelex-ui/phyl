<?php
declare (strict_types=1);

namespace app\agent\controller\api;

use think\facade\Db;
use think\facade\Request;
use app\common\service\ApiUser as S;
use app\common\model\ApiUser as M;

class UserNew extends \app\agent\controller\Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    // 新增用户列表
    public function index()
    {
        if (Request::isAjax()) {
            return $this->getJson(M::getNewUserList());
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
    // 余额充值
    public function user_recharge($id)
    {
        if (Request::isAjax()) {
            $data = Request::post();

            $set_type = '+';
            $data_type = 2;
            
            if(abs($data['price']) < 0){
                return returnJson(400, '充值金额错误');
            }

            try {
                $result = Db::name('api_user')
                    ->where('id', $id)
                    ->update(
                        [
                            'price' => Db::raw('price'.$set_type.$data['price']),
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
                        'remarks' => '用户充值' . $data['price'],
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
                $date = time();
                if(date('d') >=10){
                    $month_start = strtotime(date("Y-m-10 00:00:00"));
                    $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 month')));
                } else {
                    $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month')));
                    $month_end = strtotime(date("Y-m-9 23:59:59"));
                }
                
                $parent_user_id = Db::name('api_user')->where('id', $id)->value('parent_user_id');
                if($parent_user_id){
                    $count = Db::name('api_task')->where('user_id', $parent_user_id)->where('month_start', $month_start)->where('month_end', $month_end)->count();
                    if($count){
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
                            'price' => Db::raw('price'.$set_type.$data['price']),
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
                            'sign_in_balace' => Db::raw('sign_in_balace'.$set_type.$data['sign_in_balace']),
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
    
    public function funds(){
        $id = $this->request->param('id');
        $user = Db::name('api_user')->where('id', $id)->find();
        $sub = Db::name('api_subscription')->where('user_id', $user['id'])->where('product_type', 1)->where('delete_time is null')->where('status', 1)->find();
        $daitixian = 0.00;
        if($sub){
            $daitixian = ($sub['price']/2) + ($sub['price']*$sub['proceeds']/100/2);
        } 
        $this->assign('user', $user);
        $this->assign('daitixian', $daitixian);
        $this->assign('id', $id);
        return $this->fetch();
    }
    
    public function pending_withdrawal(){
        $id = $this->request->param('id');
        
        if (Request::isAjax()) {
            $data = Request::post();
            try {
                $result = Db::name('api_user')->where('id', $id)->update([
                    'pending_withdrawal' => Db::raw('pending_withdrawal-'.abs($data['pending_withdrawal'])),
                    'price' => Db::raw('price+'.abs($data['pending_withdrawal']))
                ]);
                if (!$result) {
                    return returnJson(400, '操作失败');
                }
                $result = Db::name('api_fund_detail')->insert([
                    'data_type' => 9,
                    'user_id' => $id,
                    'price' => abs($data['pending_withdrawal']),
                    'status' => 1,
                    'remarks' => '后台操作爱国产品待提现减少' . abs($data['pending_withdrawal'])."，账户余额增加".abs($data['pending_withdrawal']),
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
                                    'register_price' => Db::raw('register_price'.$set_type.$data['price']),
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
                        'integral' => Db::raw('integral'.$set_type.$data['integral']),
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
                        'grow_up' => Db::raw('grow_up'.$set_type.$data['grow_up']),
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
        if (Request::isAjax()){
            return $this->getJson(S::goAdd(Request::post()));
        }

        return $this->fetch('');
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

        if ($data['parent_user_id']){
            $temp = M::find($data['parent_user_id']);
            if(!empty($temp)){
                $data['parent_user_mobile'] = $temp['mobile'];
            } else {
                $data['parent_user_mobile'] = '';
            }
            
        }

        $temp = Db::name('api_account')
            ->where('user_id',$id)
            ->where('delete_time IS NULL')
            ->find();
        if ($temp){
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
