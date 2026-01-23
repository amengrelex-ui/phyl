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

    public function laicai2notify(Request $request)
    {
        // 根据API文档，回调使用POST方式，Content-Type: application/x-www-form-urlencoded
        $data = $request->param();
        
        // 记录回调日志
        $path = "./logs";
        if (!file_exists($path)) {
            mkdir("$path", 0700);
        }
        $myfile = fopen("{$path}/laicai2_notify.txt", "a");
        fwrite($myfile, date('Y-m-d H:i:s') . " - " . json_encode($data) . PHP_EOL);
        
        if (empty($data)) {
            fwrite($myfile, "回调数据为空\n");
            fclose($myfile);
            exit('fail');
        }

        // 获取商户订单号（必填参数）
        $order_no = isset($data['mchOrderNo']) ? trim($data['mchOrderNo']) : '';
        if (empty($order_no)) {
            fwrite($myfile, "商户订单号为空\n");
            fclose($myfile);
            exit('fail');
        }

        // 查找订单（幂等性处理：检查订单是否已处理）
        $info = Db::name('api_fund_detail')->where('order_no', $order_no)->find();
        if (!$info) {
            fwrite($myfile, "未找到订单: {$order_no}\n");
            fclose($myfile);
            exit('fail');
        }

        // 幂等性处理：如果订单已经是成功状态，直接返回success
        if ($info['status'] == 1) {
            fwrite($myfile, "订单已处理，幂等性返回success: {$order_no}\n");
            fclose($myfile);
            exit('success');
        }

        // 订单状态必须是待支付状态(3)
        if ($info['status'] != 3) {
            fwrite($myfile, "订单状态异常: {$order_no}, status={$info['status']}\n");
            fclose($myfile);
            exit('fail');
        }

        // 获取商户配置
        $pay_info = Db::name('api_paylist')->where('payname', 'laicai2pay')->find();
        if (!$pay_info || empty($pay_info['appkey'])) {
            fwrite($myfile, "未找到支付配置\n");
            fclose($myfile);
            exit('fail');
        }

        // 验证签名（必填参数）
        $pay_sign = isset($data['sign']) ? trim($data['sign']) : '';
        if (empty($pay_sign)) {
            fwrite($myfile, "签名为空\n");
            fclose($myfile);
            exit('fail');
        }

        // 准备签名数据（排除sign字段）
        $sign_data = $data;
        unset($sign_data['sign']);
        
        // 使用generate_signature函数验证签名
        $calc_sign = generate_signature($sign_data, $pay_info['appkey']);
        if (strtoupper($calc_sign) != strtoupper($pay_sign)) {
            fwrite($myfile, "签名验证失败: 计算签名={$calc_sign}, 回调签名={$pay_sign}\n");
            fwrite($myfile, "签名数据: " . json_encode($sign_data) . "\n");
            fclose($myfile);
            exit('fail');
        }

        // 验证订单状态（必填参数）
        // state: 1=支付中, 2=支付成功, 3=支付失败, 5=测试冲正, 6=订单关闭, 7=出码失败
        // 2和5均为支付成功
        $state = isset($data['state']) ? intval($data['state']) : 0;
        
        if ($state != 2 && $state != 5) {
            fwrite($myfile, "订单状态未成功: state={$state}, 订单号={$order_no}\n");
            fclose($myfile);
            // 根据文档，即使状态不是成功，也要返回success表示已收到通知，避免重复通知
            // 但这里我们只处理成功的情况，失败的情况记录日志但不更新订单
            exit('success');
        }

        // 验证金额（必填参数，单位：分）
        $callback_amount_fen = isset($data['amount']) ? intval($data['amount']) : 0;
        if ($callback_amount_fen <= 0) {
            fwrite($myfile, "回调金额无效: amount={$callback_amount_fen}\n");
            fclose($myfile);
            exit('fail');
        }

        // 将分转换为元进行比较
        $callback_amount_yuan = $callback_amount_fen / 100;
        
        // 允许0.01元的误差
        if (abs($callback_amount_yuan - $info['price']) > 0.01) {
            fwrite($myfile, "金额不匹配: 回调金额={$callback_amount_yuan}元({$callback_amount_fen}分), 订单金额={$info['price']}元\n");
            fclose($myfile);
            exit('fail');
        }

        // 验证商户号（必填参数）
        $mchNo = isset($data['mchNo']) ? trim($data['mchNo']) : '';
        if (empty($mchNo) || $mchNo != $pay_info['mchid']) {
            fwrite($myfile, "商户号不匹配: 回调商户号={$mchNo}, 配置商户号={$pay_info['mchid']}\n");
            fclose($myfile);
            exit('fail');
        }

        // 更新订单状态并处理用户余额
        Db::startTrans();
        try {
            // 再次检查订单状态，防止并发重复处理
            $check_info = Db::name('api_fund_detail')
                ->where('order_no', $order_no)
                ->where('status', 3)
                ->lock(true)
                ->find();
            
            if (!$check_info) {
                Db::rollback();
                fwrite($myfile, "订单已被处理（并发检查）: {$order_no}\n");
                fclose($myfile);
                exit('success');
            }

            // 更新订单状态
            $update_result = Db::name('api_fund_detail')
                ->where('order_no', $order_no)
                ->where('status', 3)
                ->update([
                    'status' => 1, 
                    'remarks' => str_replace('：', '', $info['remarks']) . '|支付系统订单号:' . (isset($data['payOrderId']) ? $data['payOrderId'] : '')
                ]);
            
            if (!$update_result) {
                Db::rollback();
                fwrite($myfile, "更新订单状态失败: {$order_no}\n");
                fclose($myfile);
                exit('fail');
            }

            // 处理用户余额
            $amount = $info['price'];
            $this->handle($info['user_id'], $amount, $info);
            
            Db::commit();
            
            fwrite($myfile, "处理成功: 订单号={$order_no}, 金额={$amount}元, 支付系统订单号=" . (isset($data['payOrderId']) ? $data['payOrderId'] : '') . "\n");
            fclose($myfile);
            
            // 根据API文档，必须返回小写的"success"字符串
            exit('success');
            
        } catch (\Exception $e) {
            Db::rollback();
            fwrite($myfile, "处理异常: " . $e->getMessage() . "\n");
            fclose($myfile);
            exit('fail');
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
