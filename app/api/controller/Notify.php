<?php

namespace app\api\controller;


use think\captcha\facade\Captcha;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Validate;
use think\Request;

class Notify extends \app\AdminBaseController
{

    public function notify(Request $request)
    {
        $data = $request->param();
        if ($data) {
            $info = Db::name('api_fund_detail')->where('order_no', $data['mchOrderNo'])->where('status', 3)->find();
            if (!$info) {
                return;
            }
            $amount = $info['price'];
            $state = Db::name('api_fund_detail')->where('order_no', $data['mchOrderNo'])->data(['status' => 1, 'remarks' => str_replace('：', '', $info['remarks'])])->update();
            if ($state) {
                // Db::name('api_user')->where('id',$info['user_id'])->inc('price',$amount)->update();
                $this->handle($info['user_id'], $amount, $info);
                echo 'success';
                exit;
            }
            echo 'fail';
        }
    }

    public function notifys(Request $request)
    {
        $data = $request->param();
        if ($data) {
            $info = Db::name('api_fund_detail')->where('order_no', $data['orderid'])->where('status', 3)->find();
            if (!$info) {
                return;
            }
            $amount = $info['price'];
            $state = Db::name('api_fund_detail')->where('order_no', $data['orderid'])->data(['status' => 1, 'remarks' => str_replace('：', '', $info['remarks'])])->update();
            if ($state) {
                $this->handle($info['user_id'], $amount, $info);
                echo 'success';
                exit;
            }
            echo 'fail';
        }
    }

    public function hynotify(Request $request)
    {
        $data = $request->param();
        if ($data['status'] == '0'){
            $info = Db::name('api_fund_detail')->where('order_no', $data['obid'])->where('status', 3)->find();
            if (!$info) {
                return;
            }
            $amount = $info['price'];
            $state = Db::name('api_fund_detail')->where('order_no', $data['obid'])->data(['status' => 1, 'remarks' => str_replace('：', '', $info['remarks'])])->update();
            if ($state) {
                $this->handle($info['user_id'], $amount, $info);
                exit('success');
            }
            exit('fail');
        }
    }

    public function pdnotify(Request $request)
    {
        $data = $request->param();
        // file_put_contents('pdnotify.txt',var_export($data,1).PHP_EOL,FILE_APPEND);
        if ($data) {
            $info = Db::name('api_fund_detail')->where('order_no', $data['merchantOrderNo'])->where('status', 3)->find();
            if (!$info) {
                return;
            }
            $amount = $info['price'];
            $state = Db::name('api_fund_detail')->where('order_no', $data['merchantOrderNo'])->data(['status' => 1, 'remarks' => str_replace('：', '', $info['remarks'])])->update();
            if ($state) {
                // Db::name('api_user')->where('id',$info['user_id'])->inc('price',$amount)->update();
                $this->handle($info['user_id'], $amount, $info);
                exit('OK');
            }
            exit('fail');
        }
    }

    public function dpdnotify(Request $request)
    {
        $data = $request->param();
        if ($data) {
            $info = Db::name('api_fund_detail')->where('order_no', $data['out_trade_no'])->where('status', 3)->find();
            if (!$info) {
                return;
            }
            $amount = $info['price'];
            $state = Db::name('api_fund_detail')->where('order_no', $data['out_trade_no'])->data(['status' => 1, 'remarks' => str_replace('：', '', $info['remarks'])])->update();
            if ($state) {
                // Db::name('api_user')->where('id', $info['user_id'])->inc('price', $amount)->update();
                $this->handle($info['user_id'], $amount, $info);
                exit('success');
            }
            exit('fail');
        }
    }

    public function dfnotify(Request $request)
    {
        $data = $request->param();
        if ($data) {
            $info = Db::name('api_fund_detail')->where('order_no', $data['out_trade_no'])->where('status', 4)->find();
            if (!$info) {
                return;
            }
            if ($data['refCode'] == '4') {
                Db::name('api_fund_detail')->where('order_no', $info['order_no'])->update(['status' => 5, 'reason' => '打款中']);
                exit('success');
            } else {
                $state = Db::name('api_fund_detail')->where('order_no', $data['out_trade_no'])->data(['status' => 1, 'reason' => '自动下发'])->update();
                if ($state) {
                    echo 'success';
                    exit;
                }
                echo 'fail';
            }
        }
    }

    public function handle($user_id, $amount, $info)
    {
        Db::startTrans();
        try {
            $user = Db::name('api_user')->where('id', $user_id)->field('mobile,withdraw_price, parent_user_id')->find();
            $withdrawPrice = (float)$user['withdraw_price'];
            $config = config('web');
            // $outPrice = min($withdrawPrice, (float)($amount * $config['recharge_rate']));
            // $outPrice = (float)($amount * $config['recharge_rate']);
            // $orderNo = $info['order_no'];
            if ($withdrawPrice > 0) {
                // Db::name('api_fund_detail')->insert([
                //     'data_type'   => 28,
                //     'user_id'     => $user_id,
                //     'price'       => $outPrice,
                //     'status'      => 1,
                //     'remarks'     => $user['mobile'] . "：充值，释放：{$outPrice}元|{$orderNo}",
                //     'create_time' => date('Y-m-d H:i:s', time()),
                // ]);
            }
            Db::name('api_user')
                ->where('id', $user_id)
                // ->dec('withdraw_price', $outPrice)
                // ->inc('price', (float) ($amount + $outPrice))
                ->inc('price', (float) $amount)
                ->inc('grow_up', (int)$amount)
                ->update();

            // $parent_id = Db::name('api_user')->where('id', $user_id)->value('parent_user_id');
            // if ($parent_id) {
            //     $pwithdraw_price = Db::name('api_user')->where('id', $parent_id)->value('withdraw_price');
            //     $outParentPrice = min($pwithdraw_price, (float)($amount * $config['recharge_parent_rate']));
            //     if ($outParentPrice) {
            //         Db::name('api_fund_detail')->insert([
            //             'data_type'   => 28,
            //             'user_id' => $parent_id,
            //             'price' => $outParentPrice,
            //             'status' => 1,
            //             'remarks' => $user['mobile'] . "：充值，上级释放{$outParentPrice}元|" . $info['order_no'],
            //             'create_time' => date('Y-m-d H:i:s'),
            //         ]);
            //         Db::name('api_user')->where('id', $parent_id)
            //         // ->dec('withdraw_price', (float)($outParentPrice))
            //         ->inc('cash_price', (float)($outParentPrice))
            //         ->update();
            //     }
            // }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }
}
