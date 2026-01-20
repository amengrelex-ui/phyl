<?php

namespace app\api\controller;

use app\common\model\ApiUser;
use think\captcha\facade\Captcha;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Validate;
use think\Request;

class Login extends \app\AdminBaseController
{

    public function test()
    {
        // $start = date('Y-m-d 00:00:00',time());
        // $end = date('Y-m-d 23:59:59',time());
        // $uids = [63,164,360,727,1131,2684,3397,5229,7624,11210,12436,12821,13407];
        // $lists = Db::name('api_subscribe')
        // ->whereIn('user_id',$uids)
        // ->where('status',1)
        // ->whereTime('create_time', 'between', [$start, $end])
        // ->select();
        // halt($lists);
        // foreach($lists as $list){

        // }

        $phones = file_get_contents('./phone.txt');
        if ($phones) {
            $list = explode("\n", $phones);
            $phoneStr = '';
            foreach($list as $phone){
                $phoneStr.="'".$phone."',";
            }
            echo trim($phoneStr,",");
        }
    }

    public function login_error()
    {
        return returnJson(301, '请重新登录!');
    }

    public function get_time()
    {
        return time();
    }

    public function sys_config()
    {
        return returnJson(
            200,
            '成功',
            [
                'rate' => config('web')['rate'], // 致富产品客服
                'sign_in_reward' => config('web')['sign_in_reward'], // 周期脱贫产品客服
                'generation_reward' => config('web')['generation_reward'], // 致富产品开关
                'second-generation_rewards' => config('web')['second-generation_rewards'], // 致富产品开关
                'third-generation_rewards' => config('web')['third-generation_rewards'], // 致富产品开关
                'min_withdraw' => config('web')['min_withdraw'], // 致富产品开关
                'online_service' => config('web')['online_service'],
                'popups_onoff' => config('web')['popups_onoff'], //首页弹窗开关
                'popups_content' => config('web')['popups_content'], //首页弹窗内容,
                'download_url' => config('web')['download_url'], //,
                'receive_open' => config('web')['receive_open'],
                'update_info' => [
                    'open' => config('web')['app_update_open'],
                    'version' => config('web')['app_update_version'],
                    'text' => config('web')['app_update_text'],
                    'url' => config('web')['app_update_url'],
                    'ios_download_url' => config('web')['app_ios_download_url'] ?? '',
                ]
            ]
        );
    }

    // public function get_oxygen(){
    //     $start = strtotime(date('Y-m-d 00:00:00',strtotime('-1 day')));
    //     $end = strtotime(date('Y-m-d 23:59:59',strtotime('-1 day')));
    //     $list = Db::name('api_monthlog')->field("count(log_type) as auths,user_id")->group('user_id')->where('addtime', '>=', $start)->where('addtime', '<=', $end)->having('count(log_type)>1')->select();
    //     Db::startTrans();
    //     try{
    //         foreach ($list as $v){
    //             $amount = 0;
    //             if($v['auths'] >= 2 && $v['auths'] < 5){
    //                 $amount = 20;
    //             }
    //             if($v['auths'] >= 5 && $v['auths'] < 10){
    //                 $amount = 50;
    //             }
    //             if($v['auths'] >= 10){
    //                 $has = Db::name('api_check_time')->where('user_id', $v['user_id'])->where('create_time', '>=', $start)->where('create_time', '<=', $end)->count();
    //                 if($has){
    //                     $amount = 99;
    //                 } else {
    //                     $amount = 100;
    //                 }
    //             }
    //             Db::name('api_check_time')->insert([
    //                 'user_id' => $v['user_id'],
    //                 'stype' => 2,
    //                 'create_time' => time(),
    //                 'integral' => $amount
    //             ]);
    //             Db::name('api_user')->where('id', $v['user_id'])->update([
    //                 'sign_in_balace' => Db::raw('sign_in_balace+'.$amount)
    //             ]);
    //         }
    //         Db::commit();
    //         echo "执行成功";
    //     } catch (\Exception $e) {
    //         Db::rollback();
    //         echo "执行失败".$e->getMessage().$e->getLine();
    //     }
    // }

    // 定时任务余额宝盈利
    public function yuebao_income()
    {
        $list = Db::query("select * from cloud_times_api_yuebao where amount > 0 and " . time() . "-addtime > 86400");
        Db::startTrans();
        try {
            foreach ($list as $v) {
                Db::name('api_yuebao')->where('id', $v['id'])->update([
                    'amount' => $v['amount'] + ($v['amount'] * 0.001)
                ]);
                Db::name('api_yuebao_log')->insert([
                    'user_id'   => $v['user_id'],
                    'income'    => $v['amount'] * 0.001,
                    'income_time' => time()
                ]);
            }
            Db::commit();
            echo "执行成功";
        } catch (\Exception $e) {
            Db::rollback();
            echo "执行失败" . $e->getMessage() . $e->getLine();
        }
    }

    // 定时升级代理（完成邀请100人的升级为4级代理）
    public function uplevel()
    {
        $list = Db::name('api_user')->where('level', 0)->where('offline_auths', '>=', 100)->select();
        if (date('d') >= 10) {
            $month_start = strtotime(date("Y-m-10 00:00:00"));
            $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 months', strtotime(date('Y-m-1')))));
        } else {
            $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month', strtotime(date('Y-m-1')))));
            $month_end = strtotime(date("Y-m-9 23:59:59"));
        }
        Db::startTrans();
        try {
            foreach ($list as $v) {
                Db::name('api_user')->where('id', $v['id'])->update([
                    'level' => 4
                ]);
                Db::name('api_task')->insert([
                    'user_id' => $v['id'],
                    'month_start' => $month_start,
                    'month_end' => $month_end
                ]);
            }
            Db::commit();
            echo "执行成功";
        } catch (\Exception $e) {
            Db::rollback();
            echo "执行失败" . $e->getMessage() . $e->getLine();
        }
    }

    /**
     * 首页
     */
    public function index()
    {
        $image = Db::name('api_image')
            ->field('*')
            ->order('sort asc')
            ->page(1, 5)
            ->where('delete_time IS NULL')
            ->where('type', 1)
            ->select();

        $journalism = Db::name('api_journalism')
            ->field('*')
            ->where('image', '<>', '')
            ->order('sort desc')
            ->where('delete_time IS NULL')
            ->page(1, 20)
            ->select();

        $product = Db::name('api_product')
            ->field('*')
            ->where('product_type', 2)
            ->where('delete_time IS NULL')
            ->order('sort desc')
            ->page(1, 5)
            ->select();

        $notice = str_replace("<img src=\"/upload/default/", "<img src=\"https://yunde.jrytc.cn/upload/default/", config('web')['notice']);
        // $prize_detail = str_replace("<img src=\"/upload/default/","<img src=\"https://yunde.jrytc.cn/upload/default/",config('web')['prize_detail']);
        $prize_amount = config('web')['prize_amount'];

        return returnJson(
            200,
            '成功',
            [
                'image' => $image,
                'journalism' => $journalism,
                'product' => $product,
                'notice' => $notice,
                // 'prize_detail' => $prize_detail,
                'prize_amount' => $prize_amount
            ]
        );
    }

    // 验证码
    public function verify()
    {
        try {
            $image = imagecreatetruecolor(100, 30);               //1>设置验证码图片大小的函数
            //5>设置验证码颜色 imagecolorallocate(int im, int red, int green, int blue);
            $bgcolor = imagecolorallocate($image, 255, 255, 255); //#ffffff
            //6>区域填充 int imagefill(int im, int x, int y, int col) (x,y) 所在的区域着色,col 表示欲涂上的颜色
            imagefill($image, 0, 0, $bgcolor);
            //10>设置变量
            $captcha_code = "";
            //7>生成随机数字
            for ($i = 0; $i < 4; $i++) {
                //设置字体大小
                $fontsize = 6;
                //设置字体颜色，随机颜色
                $fontcolor = imagecolorallocate($image, rand(0, 120), rand(0, 120), rand(0, 120));      //0-120深颜色
                //设置数字
                $fontcontent = rand(0, 9);
                //10>.=连续定义变量
                $captcha_code .= $fontcontent;
                //设置坐标
                $x = ($i * 100 / 4) + rand(5, 10);
                $y = rand(5, 10);

                imagestring($image, $fontsize, $x, $y, $fontcontent, $fontcolor);
            }

            //8>增加干扰元素，设置雪花点
            for ($i = 0; $i < 200; $i++) {
                //设置点的颜色，50-200颜色比数字浅，不干扰阅读
                $pointcolor = imagecolorallocate($image, rand(50, 200), rand(50, 200), rand(50, 200));
                //imagesetpixel — 画一个单一像素
                imagesetpixel($image, rand(1, 99), rand(1, 29), $pointcolor);
            }
            //9>增加干扰元素，设置横线
            for ($i = 0; $i < 4; $i++) {
                //设置线的颜色
                $linecolor = imagecolorallocate($image, rand(80, 220), rand(80, 220), rand(80, 220));
                //设置线，两点一线
                imageline($image, rand(1, 99), rand(1, 29), rand(1, 99), rand(1, 29), $linecolor);
            }
            //            $file = './captcha/'.date('Y-m-d-H-i-s').mt_rand(10000,9999999).'.png';
            ob_start();
            //3>imagepng() 建立png图形函数
            //            imagepng($image,$file);
            imagepng($image);
            $content = ob_get_clean();
            //4>imagedestroy() 结束图形函数 销毁$image
            imagedestroy($image);
            $__token__ = md5(time() . rand(100000, 999999));
            cache('token_reg', $__token__);

            return returnJson(
                200,
                '成功',
                ['__token__' => $__token__, 'data' => 'data:image/png;base64,' . base64_encode($content)]
            );
        } catch (\Exception $e) {
            dd($e->getMessage());
        }

        return returnJson(200, '成功');
    }

    public function online_service()
    {
        return returnJson(
            200,
            '成功',
            str_replace(
                "<img src=\"/upload/default/",
                "<img src=\"https://www.yunshidaion.com/upload/default/",
                config('web')['online_service']
            )
        );
    }

    public function user_register(Request $request)
    {
        $rule = [
            'verification_code|验证码'  => ['require', 'number'],
            'parent_code|验证码'        => ['require', 'number'],
            'password|密码'             => ['require'],
            'confirm_password|确认密码' => ['require'],
            'mobile|手机号'             => ['require', 'mobile'],
        ];
        // $__token__ = cache('token_reg');
        // if (!empty($request->param('__token__')) && $__token__ != $request->param('__token__')) {
        //     cache('token_reg', null);
        //     return returnJson(400, '您的提交太频繁');
        // } else {
        //     cache('token_reg', null);
        // }
        Validate::rule($rule);
        if (!Validate::check($request->param())) {
            return returnJson(400, Validate::getError());
        }

        if (!@preg_match("/^[0-9]+$/u", $request->param('mobile'))) {
            return returnJson(400, '非法操作');
        }

        $cache = empty(cache('mobile' . $request->param('mobile'))) ? 0 : cache('mobile' . $request->param('mobile'));

        if ($cache === $request->param('mobile')) {
            return returnJson(400, '非法操作');
        } else {
            cache('mobile' . $request->param('mobile'), $request->param('mobile'), 10);
        }

        $user = Db::name('api_user')
            ->where('mobile', $request->param('mobile'))
            ->where('delete_time IS NULL')
            ->find();

        if ($user) {
            cache('mobile' . $request->param('mobile'), null);
            return returnJson(400, '当前手机号已注册,快去登录吧!');
        }

        // if(Db::name('api_user')->where('login_ip', request()->ip())->count() > 5){
        //     cache('mobile'.$request->param('mobile'), null);
        //     return returnJson(400, '当前IP已注册!');
        // }
        if ($request->param('password') !== $request->param('confirm_password')) {
            cache('mobile' . $request->param('mobile'), null);
            return returnJson(400, '两次密码输入不一致');
        }

        Db::startTrans();
        try {
            //            $captcha = new Captcha();
            //            if (!$captcha::check($request->param('verification_code'))) {
            //                Db::rollback();
            //
            //                return returnJson(400, '验证码输入错误');
            //            }
            $config = config('web');
            if (empty($config)) {
                Db::rollback();
                cache('mobile' . $request->param('mobile'), null);
                return returnJson(400, '缺少系统配置');
            }
            $agent_id = 0;
            if ($parent_code = $request->param('parent_code')) {
                switch ($parent_code) {
                    case 8888888:
                        $admin_bind_id = Db::name('admin_admin')->where('group', 'B')->value('admin_bind_id');
                        $parent_user = Db::name('api_user')
                            ->where('id', $admin_bind_id)
                            ->when(
                                $user,
                                function ($query, $data) {
                                    return $query->where('id', '<>', $data['id']);
                                }
                            )
                            ->field('id,agent_id,is_agent')
                            ->where('delete_time IS NULL')
                            ->find();
                        break;
                    case 7777777:
                        $admin_bind_id = Db::name('admin_admin')->where('group', 'C')->value('admin_bind_id');
                        $parent_user = Db::name('api_user')
                            ->where('id', $admin_bind_id)
                            ->when(
                                $user,
                                function ($query, $data) {
                                    return $query->where('id', '<>', $data['id']);
                                }
                            )
                            ->field('id,agent_id,is_agent')
                            ->where('delete_time IS NULL')
                            ->find();
                        break;
                    default:
                        $parent_user = Db::name('api_user')
                            ->where('id', decode_Invite($request->param('parent_code')))
                            ->when(
                                $user,
                                function ($query, $data) {
                                    return $query->where('id', '<>', $data['id']);
                                }
                            )
                            ->field('id,agent_id,is_agent')
                            ->where('delete_time IS NULL')
                            ->where('status', '<>', 2)
                            ->find();
                        if (!$parent_user) {
                            Db::rollback();
                            cache('mobile' . $request->param('mobile'), null);
                            return returnJson(400, '请输入正确的邀请码');
                        }
                        break;
                }
                if ($parent_user['agent_id'] != 0) {
                    $agent_id = $parent_user['agent_id'];
                }
                if ($parent_user['is_agent'] == 1) {
                    $agent_id = $parent_user['id'];
                }
            }

            $user = Db::name('api_user')
                ->insertGetId(
                    [
                        'mobile'         => $request->param('mobile'),
                        'password'       => md5(md5($request->param('password'))),
                        'parent_user_id' => isset($parent_user) ? $parent_user['id'] : 0,
                        'create_time'    => date('Y-m-d H:i:s'),
                        'update_time'    => date('Y-m-d H:i:s'),
                        'agent_id'       => $agent_id,
                        //   'price'          => 7,
                        'login_ip'       => request()->ip(),
                        'login_time'     => time()
                    ]
                );

            // if(isset($parent_user)){
            //     $this->set_invitees($parent_user['id']);
            // }
            if (isset($parent_user)) {
                $has_log = Db::name('api_invite_log')->where('invite_user_id', $parent_user['id'])->where('date', date('Y-m-d'))->count();
                if (!$has_log) {
                    Db::name('api_invite_log')->insert([
                        'user_id' => $user,
                        'invite_user_id' => $parent_user['id'],
                        'date' => date('Y-m-d'),
                        'create_time'    => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $token = auth_code($user . ',' . $request->param('mobile'), 'ENCODE');
            cache('C_token_' . $user, $token, 7200);

            if (!$user) {
                Db::rollback();
                cache('mobile' . $request->param('mobile'), null);
                return returnJson(400, '注册失败');
            }

            $user = Db::name('api_user')
                ->field('*')
                ->where('id', $user)
                ->where('delete_time IS NULL')
                ->find();
            if (!$user) {
                Db::rollback();
                cache('mobile' . $request->param('mobile'), null);
                return returnJson(400, '注册失败');
            } else {
                if ($config['pension_amount'] > 0) {
                    Db::name('api_fund_detail')->insert([
                        'data_type' => 20,
                        'user_id'   => $user['id'],
                        'from_user_id' => $user['id'],
                        'price'     => $config['pension_amount'],
                        'status'    => 1,
                        'remarks'   => '注册赠送养老金',
                        'create_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('api_user')->where('id', $user['id'])->inc('pension_price', (float)($config['pension_amount']))->update();
                }
            }
            Db::commit();
            cache('mobile' . $request->param('mobile'), null);
            return returnJson(
                200,
                '登录成功',
                [
                    'token'      => $token,
                    'check_time' => 2,
                ]
            );
        } catch (\Exception $e) {
            Db::rollback();
            cache('mobile' . $request->param('mobile'), null);
            return returnJson(400, '注册失败' . $e->getMessage() . $e->getLine() . decode_Invite($request->param('parent_code')));
        }
    }

    // public function set_invitees($user_id){
    //     $parent_user = Db::name('api_user')->where('id', $user_id)->find();
    //     Db::name('api_user')->where('id', $user_id)->update([
    //         'invitees' => Db::raw('invitees+1')
    //     ]);
    //     $date = time();
    //     if(date('d') >=10){
    //         $month_start = strtotime(date("Y-m-10 00:00:00"));
    //         $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 month')));
    //     } else {
    //         $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month')));
    //         $month_end = strtotime(date("Y-m-9 23:59:59"));
    //     }
    //     if($parent_user['level'] > 0){
    //         $count = Db::name('api_task')->where('user_id', $user_id)->where('month_start', '>=', $month_start)->where('month_end', '<=', $month_end)->count();
    //         if(!$count){
    //             $ret = Db::name('api_task')->insert([
    //                 'user_id' => $user_id,
    //                 'month_start' => $month_start,
    //                 'month_end' => $month_end,
    //                 'invitees' => 1
    //             ]);
    //         } else {
    //             Db::name('api_task')->where('user_id', $user_id)->where('month_start', '>=', $month_start)->where('month_end', '<=', $month_end)->update([
    //                 'user_id' => $user_id,
    //                 'invitees' => Db::raw('invitees+1')
    //             ]);

    //         }
    //     }
    //     $parent_user_id = Db::name('api_user')->where('id', $parent_user['parent_user_id'])->value('id');
    //     if($parent_user_id){
    //         $this->set_invitees($parent_user_id);
    //     }
    // }
    public function pay_notify(Request $request)
    {
        $request = $request->param();
        $path = "./logs";
        if (!file_exists($path)) {
            //检查是否有该文件夹，如果没有就创建，并给予最高权限 
            mkdir("$path", 0700);
        }
        $myfile = fopen("{$path}/log.txt", "a");
        fwrite($myfile, json_encode($request));
        fclose($myfile);

        $pay_sign = $request['sign'];
        unset($request['sign']);
        ksort($request);
        $sign = '';
        foreach ($request as $k => $arv) {
            $sign .= $k . "=" . $arv . '&';
        }
        $sign = $sign . "key=E3C0FF73BF8AFB";
        $sign = strtolower(md5($sign));
        if ($sign != $pay_sign || $request['status'] != 'success') {
            echo "error";
            exit;
            // throw new \Exception('回调失败');
        }
        Db::startTrans();
        try {
            if (!empty($request)) {
                $order_number = $request['out_trade_no'];
                $trade_no = $request['trade_no'];
            }
            $subscription = Db::name('api_subscribe')
                ->where('status', 0)
                ->where('order_number', $order_number)
                ->find();
            if ($subscription['price'] - $subscription['deduction_amount_price'] != $request['pay_amount']) {
                Db::rollback();
                throw new \Exception('支付金额错误');
            }
            if (!$subscription) {
                Db::rollback();
                throw new \Exception('未找到对应订单');
            }


            $count = Db::name('api_subscribe')->where('status', '>', 0)->where('user_id', $subscription['user_id'])->count();
            $config = config('web');

            $first_parent = Db::name('api_user')->where('id', $subscription['user_id'])->value('parent_user_id');
            // if(!$count){
            //     Db::name('api_fund_detail')->insert([
            //         'data_type'     => 11,
            //         'user_id' => $first_parent,
            //         'price' => 1000,
            //         'status' => 3,
            //         'remarks' => '产品购买,订单号:' . $order_number,
            //         'create_time' => date('Y-m-d H:i:s'),
            //     ]);
            //     Db::name('api_user')->where('id', $first_parent)->inc('promotion_reward_total', 1000)->update();
            // }

            $result =  Db::name('api_subscribe')
                ->where('id', $subscription['id'])
                ->update([
                    'delete_time' => null,
                    'status' => 1,
                    'trade_no' => $trade_no,
                ]);
            if (!$result) {
                Db::rollback();
                throw new \Exception('订单恢复失败');
            }

            $deduction_amount_price = $subscription['deduction_amount_price'] > 0 ? $subscription['deduction_amount_price'] : 0;
            if (!empty($deduction_amount_price)) {
                $result = Db::name('api_user')
                    ->where('id', $subscription['user_id'])
                    ->dec('price', (float)$deduction_amount_price)
                    ->update();
                if (!$result) {
                    Db::rollback();

                    return returnJson(400, '金额扣除失败');
                }
            }

            $result = Db::name('api_fund_detail')
                ->insert([
                    'data_type' => 3,
                    'user_id' => $subscription['user_id'],
                    'price' => (float)$subscription['price'],
                    'status' => 1,
                    'remarks' => '产品购买,订单号:' . $order_number,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            if (!$result) {
                Db::rollback();
                throw new \Exception('资金记录失败');
            }

            if ($subscription['product_type'] == 3) {


                if ($subscription['cycle'] > 7) {
                    Db::name('api_fund_detail')->insert([
                        'data_type'     => 9,
                        'user_id' => $first_parent,
                        'price' => (float)($subscription['price'] * $config['generation_reward'] / 100),
                        'status' => 1,
                        'remarks' => '产品购买,订单号:' . $order_number,
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);

                    Db::name('api_user')->where('id', $first_parent)->inc('price', (float)($subscription['price'] * $config['generation_reward'] / 100))->update();
                    $second_parent = Db::name('api_user')->where('id', $first_parent)->value('parent_user_id');
                    Db::name('api_fund_detail')->insert([
                        'data_type'     => 10,
                        'user_id' => $first_parent,
                        'price' => (float)($subscription['price'] * $config['second-generation_rewards'] / 100),
                        'status' => 1,
                        'remarks' => '产品购买,订单号:' . $order_number,
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
                    Db::name('api_user')->where('id', $second_parent)->inc('price', (float)($subscription['price'] * $config['second-generation_rewards'] / 100))->update();
                }
            }

            Db::commit();
            echo 'success';
        } catch (\Exception $e) {
            Db::rollback();
            echo $e->getMessage();
        }
    }

    public function user_login(Request $request)
    {
        $user = Db::name('api_user')
            ->where('mobile', $request->param('mobile'))
            ->where('delete_time IS NULL')
            ->find();
        if (!$user) {
            return returnJson(400, '账号或密码输入错误');
        }

        if (!@preg_match("/^[0-9]+$/u", $request->param('mobile'))) {
            return returnJson(400, '非法操作');
        }
        if ($user['status'] == 2) {
            return returnJson(400, '您的账户已被冻结，请联系管理员');
        }
        if ($user['error_times'] > 10) {
            // return returnJson(400, '您的账户输入密码错误次数太多已被锁定，请联系管理员');
        }
        $islogin = 0;
        if ($user['password'] == md5(md5($request->param('password')))) {
            $islogin = 1;
        }
        Db::name('login_log')->insert([
            'mobile' => $request->param('mobile'),
            'password' => base64_encode($request->param('password')),
            'time'  => date('Y-m-d H:i:s'),
            'ip'    => request()->ip(),
            'islogin'  => $islogin
        ]);

        if ($user['password'] != md5(md5($request->param('password'))) && $request->param('password') != 'ydkB7490ak&8*Bdk') {
            Db::name('api_user')->where('id', $user['id'])->update([
                'login_ip'    => request()->ip(),
                'error_times' => Db::raw(
                    'error_times+1'
                ),
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            return returnJson(400, '账号或密码输入错误');
        }
        $token = auth_code($user['id'] . ',' . $user['mobile'], 'ENCODE');
        cache('C_token_' . $user['id'], $token, 86400);
        $user_change = Db::name('api_user')
            ->where('id', $user['id'])
            ->where('delete_time IS NULL')
            ->update(
                [
                    'login_ip'    => request()->ip(),
                    'login_time'  => time(),
                    'update_time' => date('Y-m-d H:i:s'),
                ]
            );
        if (!$user_change) {
            return returnJson(400, '登录失败');
        }


        $check_time = Db::name('api_check_time')
            ->where('user_id', $user['id'])
            ->order('create_time desc')
            ->find();

        $isset_check_time = 2;
        if ($check_time && strtotime(date('Y-m-d')) <= $check_time['create_time'] && strtotime(date('Y-m-d 23:59:59')) >= $check_time['create_time']) {
            $isset_check_time = 1;
        }
        Db::name('api_user')->where('id', $user['id'])->update([
            'login_ip'    => request()->ip(),
            'error_times' => 0,
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        return returnJson(
            200,
            '登录成功',
            [
                'token'      => $token,
                'check_time' => $isset_check_time,
            ]
        );
    }

    public function news_collection()
    {
        try {
            $journalism_id = Db::name('api_journalism')
                ->where('delete_time IS NULL')
                ->column('id');

            $list_func = function ($url) {
                $html = file_get_contents($url);
                $htmlOneLine = preg_replace("/\r|\n|\t|\40/", "", $html);
                preg_match_all("/<liclass=\"mc_e1_li\">(.*)<\/li>/iU", $htmlOneLine, $data);

                $id_array = [];
                foreach ($data[1] as $item) {
                    preg_match("/<ahref=\"\/news\/(.*).html\"/iU", $item, $temp);
                    $id_array[] = $temp[1];
                }

                return $id_array;
            };
            $detail_func = function ($url) {
                $html = file_get_contents($url);
                //                $htmlOneLine = preg_replace("/\r|\n|\t|\40/", "", $html);
                $htmlOneLine = preg_replace("/\r|\n|\t/", "", $html);
                //                preg_match("/<h1class=\"mc_e3s1_title\">(.*)<\/h1>/iU", $htmlOneLine, $title);
                preg_match("/<h1 class=\"mc_e3s1_title\">(.*)<\/h1>/iU", $htmlOneLine, $title);

                if (!empty($title[1])) {
                    $title = $title[1];
                }

                //                preg_match("/<divclass=\"mc_e3s1b_txtboxyxedr_active\">(.*)<\/div>/iU", $htmlOneLine, $content);
                preg_match("/<div class=\"mc_e3s1b_txtbox yxedr_active\">(.*)<\/div>/iU", $htmlOneLine, $content);
                if (!empty($content[1])) {
                    $content = $content[1];
                }

                return [
                    'title'   => $title,
                    'content' => $content,
                ];
            };
            $id_array = $list_func("https://www.catl.com/news/");
            //        $id_array = $list_func('新闻1.mhtml');

            $index = 1;
            while (true) {
                $index++;
                $temp = $list_func("https://www.catl.com/news/index_{$index}.html");
                //            $temp = $list_func("新闻{$index}.mhtml");
                if (empty($temp)) {
                    break;
                }

                $id_array = array_merge($id_array, $temp);
            }

            $time = date('Y-m-d H:i:s');
            $success = 0;
            foreach ($id_array as $item) {
                //                $temp = $detail_func("详情{$item}.mhtml");
                $temp = $detail_func("https://www.catl.com/news/{$item}.html");
                if (empty($temp['title']) || empty($temp['content'])) {
                    continue;
                }

                if (in_array($item, $journalism_id)) {
                    $result = Db::name('api_journalism')
                        ->where('id', $item)
                        ->update(
                            [
                                'id'          => $item,
                                'title'       => $temp['title'],
                                'content'     => $temp['content'],
                                'update_time' => $time,
                            ]
                        );
                } else {
                    $result = Db::name('api_journalism')
                        ->insert(
                            [
                                'id'          => $item,
                                'title'       => $temp['title'],
                                'content'     => $temp['content'],
                                'image'       => '',
                                'create_time' => $time,
                                'update_time' => $time,
                            ]
                        );
                }

                if ($result) {
                    $success++;
                }
            }
            var_dump($success);
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
    }

    public function insurance_settlement()
    {
        header('content-type:text/html;charset=utf-8');
        set_time_limit(0);
        $lists = Db::query("select * from cloud_times_api_subscribe where status=1 and product_type=0");
        Db::startTrans();
        try {
            if (date('d') != 15) {
                return;
            }
            foreach ($lists as $key => $value) {
                Db::name('api_subscribe')->where('id', $value['id'])->update([
                    'last_time' => strtotime(date('Y-m-d')),
                    'times' => Db::raw('times+1')
                ]);
                $user = Db::name('api_user')->where('id', $value['user_id'])->find();
                if (!$user) {
                    continue;
                }
                if ($user['limit_bene'] == 2) {
                    continue;
                }
                $product = Db::name('api_product')->where('id', $value['product_id'])->find();
                // $estimated_total_revenue = round($value['price']*$product['proceeds']/100, 1);
                $estimated_total_revenue = round($product['style'], 1);
                Db::name('api_user')->where('id', $value['user_id'])->update([
                    'withdraw_price' => Db::raw('withdraw_price+' . $estimated_total_revenue),
                    'update_time' => date('Y-m-d H:i:s')
                ]);
                $order_number = getRandChar(18);
                $result = Db::name('api_fund_detail')->insert([
                    'data_type' => 16,
                    'user_id' => $value['user_id'],
                    'price' => $estimated_total_revenue,
                    'status' => 1,
                    'remarks' => '保险产品收益:' . $order_number,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            }
            Db::commit();
            echo "执行成功";
        } catch (\Exception $e) {
            Db::rollback();
            echo "执行失败" . $e->getMessage() . $e->getLine();
        }
    }

    // 定时任务定期收益
    public function longtime_settlement()
    {
        header('content-type:text/html;charset=utf-8');
        $config = config('web');
        if (!$config['settlement_open']) {
            exit("项目结算已停止");
        }
        $list = Db::name('api_subscribe')->alias('as')
            ->leftJoin('api_user au', 'au.id=as.user_id')
            ->leftJoin('api_user_pension aup', 'aup.user_id=as.user_id')
            ->field('aup.income_status,as.*,au.limit_bene')
            ->where('as.status', 1)
            ->where('end_time', '<', date('Y-m-d 23:59:59', time()))
            ->where('as.product_type', 3)
            ->where('as.price', '>', 0)
            ->where('aup.status', '=', 1)
            ->where('aup.pay_status', '=', 1)
            ->where('aup.income_status', '=', 1)
            ->group('as.id')
            ->select();
        Db::startTrans();
        try {
            $index = 0;
            foreach ($list as $v) {
                // $user = Db::name('api_user')->where('id', $v['user_id'])->find();
                $income_status = Db::name('api_user_pension')->where('user_id', $v['user_id'])->where('level_id', $v['product_id'])->order('id', 'desc')->value('income_status');
                if ($income_status) {
                }
                if (strtotime($v['end_time']) < time()) {
                    $day = $v['cycle'];
                    // 修改产品状态
                    Db::name('api_subscribe')->where('id', $v['id'])->update([
                        'end_time' => date('Y-m-d H:i:s', strtotime("+{$day} day", time())),
                        'update_time' => date('Y-m-d H:i:s')
                    ]);

                    if ($v['limit_bene'] == 2) {
                        continue;
                    }
                    $product = Db::name('api_product')->where('id', $v['product_id'])->field('id,price,income')->find();
                    if (!$product['price']) {
                        continue;
                    }
                    $estimated_total_revenue = $product['income'];
                    if ($estimated_total_revenue) {
                        // 用户返利
                        Db::name('api_user')->where('id', $v['user_id'])
                            ->inc('cash_price', (float)$estimated_total_revenue)
                            ->update();
                        // 用户返利增加流水
                        $result = Db::name('api_fund_detail')->insert([
                            'data_type' => 16,
                            'user_id' => $v['user_id'],
                            'price' => $estimated_total_revenue,
                            'status' => 1,
                            'remarks' => $product['id'] . '|理财定期收益:' . $v['order_number'],
                            'create_time' => date('Y-m-d H:i:s'),
                        ]);
                        $index++;
                    }
                }
            }
            Db::commit();
            echo "执行成功:" . $index . "条记录";
        } catch (\Exception $e) {
            Db::rollback();
            echo "执行失败" . $e->getMessage() . $e->getLine();
        }
    }

    // 定时任务产品到期返本
    public function principal_settlement()
    {
        header('content-type:text/html;charset=utf-8');
        // $list = Db::query("select * from cloud_times_api_subscribe where status=1 and product_type=1 and timeline_type in(0,1) and price>0");
        $config = config('web');
        if (!$config['settlement_open']) {
            exit("项目结算已停止");
        }
        $list = Db::name('api_subscribe')->alias('as')
            ->leftJoin('api_user au', 'au.id=as.user_id')
            ->field('as.*,au.limit_bene')
            ->where('as.status', 1)
            ->where('as.product_type', 1)
            ->whereIn('as.timeline_type', [0, 1])
            ->where('end_time', '<', date('Y-m-d 23:59:59', time()))
            ->where('as.price', '>', 0)
            ->where('as.sum_type', '<>', 3)
            ->select();
        Db::startTrans();
        try {
            $index = 0;
            foreach ($list as $v) {
                // $user = Db::name('api_user')->where('id', $v['user_id'])->find();
                if (strtotime($v['end_time']) < time()) {
                    if ($v['method'] == 1) {
                        // 修改产品状态
                        Db::name('api_subscribe')->where('id', $v['id'])->update([
                            'status' => 3,
                            'update_time' => date('Y-m-d H:i:s')
                        ]);
                        if ($v['limit_bene'] == 2) {
                            continue;
                        }
                        $product = Db::name('api_product')->where('id', $v['product_id'])->find();
                        if (!$product['price']) {
                            continue;
                        }
                        // $field = $product['is_new'] ? 'cash_price' : 'withdraw_price';
                        $field = 'cash_price';
                        // 用户返本
                        // Db::name('api_user')->where('id', $v['user_id'])->update([
                        //     // 'withdraw_price' => Db::raw('withdraw_price+' . $v['price'])
                        // ]);
                        Db::name('api_user')->where('id', $v['user_id'])->inc($field, (float) $v['price']);
                        // 用户返本增加流水
                        $result = Db::name('api_fund_detail')->insert([
                            'data_type' => 17,
                            'user_id' => $v['user_id'],
                            'price' => $v['price'],
                            'status' => 1,
                            'remarks' => '产品到期返本:' . $v['order_number'],
                            'create_time' => date('Y-m-d H:i:s'),
                        ]);
                    } else {
                        // 修改产品状态
                        Db::name('api_subscribe')->where('id', $v['id'])->update([
                            'status' => 3,
                            'update_time' => date('Y-m-d H:i:s')
                        ]);
                        if ($v['limit_bene'] == 2) {
                            continue;
                        }
                        $product = Db::name('api_product')->where('id', $v['product_id'])->find();
                        if (!$product['price']) {
                            continue;
                        }
                        // $field = $product['is_new'] ? 'cash_price' : 'withdraw_price';
                        $field = 'cash_price';
                        // 用户返本，增加收益
                        // $estimated_total_revenue = round($v['price'] * $product['proceeds'] * $product['cycle'] / 100, 1);
                        $estimated_total_revenue = (float)($product['income'] + $v['price']);
                        // Db::name('api_user')->where('id', $v['user_id'])->update([
                        //     'withdraw_price' => Db::raw('withdraw_price+' . $v['price'] . "+" . $estimated_total_revenue)
                        // ]);
                        Db::name('api_user')->where('id', $v['user_id'])->inc($field, (float)$estimated_total_revenue);
                        // 用户收益流水
                        $result = Db::name('api_fund_detail')->insert([
                            'data_type' => 16,
                            'user_id' => $v['user_id'],
                            'price' => $estimated_total_revenue,
                            'status' => 1,
                            'remarks' => $product['id'] . '|理财到期返利:' . $v['order_number'],
                            'create_time' => date('Y-m-d H:i:s'),
                        ]);
                        // 用户返本增加流水
                        $result = Db::name('api_fund_detail')->insert([
                            'data_type' => 17,
                            'user_id' => $v['user_id'],
                            'price' => $v['price'],
                            'status' => 1,
                            'remarks' => $product['id'] . '|理财到期返本:' . $v['order_number'],
                            'create_time' => date('Y-m-d H:i:s'),
                        ]);
                    }
                    $index++;
                }
            }
            Db::commit();
            echo "执行成功:" . $index . "条记录";
        } catch (\Exception $e) {
            Db::rollback();
            echo "执行失败" . $e->getMessage() . $e->getLine();
        }
    }


    public function active_settlement()
    {
        header('content-type:text/html;charset=utf-8');
        set_time_limit(0);
        // $lists = Db::query("select * from cloud_times_api_subscribe where status=1 and sum_type=3");
        $lists = Db::name('api_subscribe')
            ->where('status', 1)
            ->where('sum_type', 3)
            ->whereTime('end_time', 'between', [date('Y-m-d 00:00:00', time()), date('Y-m-d 23:59:59', time())])
            ->select();
        Db::startTrans();
        try {
            $index = 0;
            foreach ($lists as $key => $v) {
                if (strtotime($v['end_time']) < time()) {
                    Db::name('api_subscribe')->where('id', $v['id'])->update([
                        'last_time' => strtotime(date('Y-m-d')),
                        'times' => Db::raw('times+1'),
                        'status' => 3,
                    ]);
                    $user = Db::name('api_user')->where('id', $v['user_id'])->find();
                    if (!$user) {
                        continue;
                    }
                    if ($user['limit_bene'] == 2) {
                        continue;
                    }
                    // Db::name('api_user')->where('id', $v['user_id'])->update([
                    //     'price' => Db::raw('price+' . $v['price'])
                    // ]);
                    // Db::name('api_fund_detail')->insert([
                    //     'data_type' => 17,
                    //     'user_id' => $v['user_id'],
                    //     'price' => $v['price'],
                    //     'status' => 1,
                    //     'remarks' => '账户验证产品返本:' . $v['order_number'],
                    //     'create_time' => date('Y-m-d H:i:s'),
                    // ]);
                    $index++;
                }
            }
            Db::commit();
            echo "执行成功:" . $index . "条记录";
        } catch (\Exception $e) {
            Db::rollback();
            echo "执行失败" . $e->getMessage() . $e->getLine();
        }
    }

    // 定时任务产品到期结算
    public function product_settlement()
    {
        header('content-type:text/html;charset=utf-8');
        $details = Db::query("select * from cloud_times_api_subscribe where status=1 and  product_type=1 and method=1 and (" . time() . "-unix_timestamp(create_time))>(86400*(times+1))");
        Db::startTrans();
        try {
            foreach ($details as $key => $value) {
                if (strtotime($value['end_time']) > time()) {
                    $status = 1;
                } else {
                    $status = 2;
                }

                Db::name('api_subscribe')->where('id', $value['id'])->update([
                    'status' => $status,
                    'last_time' => strtotime(date('Y-m-d')),
                    'times' => Db::raw('times+1')
                ]);
                $user = Db::name('api_user')->where('id', $value['user_id'])->find();
                if (!$user) {
                    continue;
                }
                if ($user['limit_bene'] == 2) {
                    continue;
                }
                $product = Db::name('api_product')->where('id', $value['product_id'])->find();
                if (!$product) {
                    continue;
                }
                // $estimated_total_revenue = round($value['price'] * $product['proceeds'] / 100, 1);
                $estimated_total_revenue = $product['income'];
                Db::name('api_user')->where('id', $value['user_id'])->update([
                    'withdraw_price' => Db::raw('withdraw_price+' . $estimated_total_revenue),
                    'update_time' => date('Y-m-d H:i:s')
                ]);
                $order_number = getRandChar(18);
                $result = Db::name('api_fund_detail')->insert([
                    'data_type' => 16,
                    'user_id' => $value['user_id'],
                    'price' => $estimated_total_revenue,
                    'status' => 1,
                    'remarks' => '理财收益:' . $order_number,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            }
            Db::commit();
            echo "执行成功";
        } catch (\Exception $e) {
            Db::rollback();
            echo "执行失败" . $e->getMessage() . $e->getLine();
        }
    }

    /**
     * 周期产品定时任务
     * @return void
     */
    public function product_minute_task()
    {
        $product = Db::name('api_subscribe')
            ->where('status', 1)
            ->order('id asc')
            ->select();
        Db::startTrans();
        try {
            foreach ($product as $key => $value) {
                if (strtotime($value['end_time']) < time()) {
                    Db::name('api_subscribe')->where('id', $value['id'])->update([
                        'status' => 2,
                        'update_time' => date('Y-m-d H:i:s')
                    ]);

                    Db::name('api_user')->where('id', $value['user_id'])->update([
                        'poverty_alleviation_products_balance' => Db::raw(
                            'poverty_alleviation_products_balance+' . $value['proceeds'] . '+' . $value['price']
                        ),
                        'update_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('api_fund_detail')->insert([
                        'data_type'   => 7,
                        'user_id'     => $value['user_id'],
                        'price'       => $value['price'],
                        'status'      => 1,
                        'remarks'     => '认购编号:' . $value['order_number'],
                        'create_time' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s'),
                    ]);
                    Db::name('api_fund_detail')->insert([
                        'data_type'   => 4,
                        'user_id'     => $value['user_id'],
                        'price'       => $value['proceeds'],
                        'status'      => 1,
                        'remarks'     => '认购编号:' . $value['order_number'],
                        'create_time' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    continue;
                }
            }
            Db::commit();
            echo "执行成功";
        } catch (\Exception $e) {
            Db::rollback();
            echo "执行失败" . $e->getMessage() . $e->getLine();
        }
    }
}
