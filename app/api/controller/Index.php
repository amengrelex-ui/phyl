<?php

declare(strict_types=1);

namespace app\api\controller;

use app\admin\controller\admin\Permission;
use app\common\model\ApiProduct;
use app\common\model\ApiSubscribe;
use app\common\model\ApiUser;
use think\facade\Db;
use think\facade\Validate;
use think\Request;

class Index extends \app\BaseController
{
    protected $middleware = ['UserCheck'];

    /**
     * 首页
     */
    public function index()
    {
        $image = Db::name('api_image')
            ->field('*')
            ->order('sort desc')
            ->page(1, 5)
            ->where('delete_time IS NULL')
            ->select();

        $journalism = Db::name('api_journalism')
            ->field('*')
            ->where('image', '<>', '')
            ->order('sort desc')
            ->where('delete_time IS NULL')
            ->page(1, 5)
            ->select();

        $product = Db::name('api_product')
            ->field('*')
            ->where('product_type', 2)
            ->where('delete_time IS NULL')
            ->order('sort desc')
            ->page(1, 5)
            ->select();

        $notice = str_replace("<img src=\"/upload/default/", "<img src=\"https://www.rkslsa.com/upload/default/", config('web')['notice']);

        return returnJson(
            200,
            '成功',
            [
                'image' => $image,
                'journalism' => $journalism,
                'product' => $product,
                'notice' => $notice,
            ]
        );
    }

    public function invite_log()
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $invite_log = [
            'register' => 0,
            'realname' => 0,
            'subscribe' => 0,
            'date' => date('Y-m-d')
        ];
        $invites = Db::name('api_invite_log')->where('invite_user_id', $user['id'])->where('date', date('Y-m-d'))->select();
        if (count($invites) > 0) {
            $invite_log['register'] = 1;
            foreach ($invites as $v) {
                $has_realname = Db::name('api_user')->where('id', $v['user_id'])->where('status', 1)->count();
                if ($has_realname) {
                    $invite_log['realname'] = 1;
                }
                $has_subscribe = Db::name('api_subscribe')->where('user_id', $v['user_id'])->where('product_id', 47)->where('create_time', '>=', date('Y-m-d 00:00:00'))->where('create_time', '<=', date('Y-m-d 23:59:59'))->count();
                if ($has_subscribe) {
                    $invite_log['subscribe'] = 1;
                }
                if ($has_realname && $has_subscribe) {
                    break;
                }
            }
        }
        return returnJson(200, '成功', $invite_log);
    }

    public function invite_exchange(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $__token__ = cache('token_transfer_' . $user['id']);
        if ($__token__ != $request->param('__token__')) {
            cache('token' . $user['id'], null);
            return returnJson(400, '您的提交太频繁');
        } else {
            cache('token' . $user['id'], null);
        }

        $invite_log = [
            'register' => 0,
            'realname' => 0,
            'subscribe' => 0,
            'date' => date('Y-m-d')
        ];
        $invites = Db::name('api_invite_log')->where('invite_user_id', $user['id'])->where('date', date('Y-m-d'))->select();
        if (count($invites) > 0) {
            $invite_log['register'] = 1;
            foreach ($invites as $v) {
                $has_realname = Db::name('api_user')->where('id', $v['user_id'])->where('status', 1)->count();
                if ($has_realname) {
                    $invite_log['realname'] = 1;
                }
                $has_subscribe = Db::name('api_subscribe')->where('user_id', $v['user_id'])->where('product_id', 47)->where('create_time', '>=', date('Y-m-d 00:00:00'))->where('create_time', '<=', date('Y-m-d 23:59:59'))->count();
                if ($has_subscribe) {
                    $invite_log['subscribe'] = 1;
                }
                if ($has_realname && $has_subscribe) {
                    break;
                }
            }
        }
        $count = Db::name('api_subscribe')->where('user_id', $user['id'])->where('product_id', 37)->where('is_clock_in', 1)->count();
        if ($invite_log['register'] != 1 || $invite_log['realname'] != 1 || $invite_log['subscribe'] != 1) {
            return returnJson(400, '活动未完成');
        } else if ($count) {
            return returnJson(400, '已经领取');
        } else {
            $product = Db::name('api_product')->where('id', 37)->find();
            $estimated_total_revenue = round($product['price'] * $product['proceeds'] / 100, 1);
            $end_time = time() + (86400 * $product['cycle']);
            $order_number = getRandChar(18);
            $res = Db::name('api_subscribe')->insert([
                'user_id' => $user['id'],
                'product_id' => 37,
                'is_clock_in' => 1,
                'product_type' => $product['product_type'],
                'method' => $product['method'],
                'name' => $product['name'],
                'price' => $product['price'],
                'cycle' => $product['cycle'],
                'image' => $product['image'],
                'status' => 1,
                'proceeds' => $product['proceeds'],
                'end_time' => date('Y-m-d H:i:s', $end_time),
                'estimated_total_revenue' => $estimated_total_revenue, // 预计收入
                'cash_back_amount_per_day' => round($product['proceeds'] * $product['price'] / 100, 1),
                'username' => $user['username'],
                'id_card' => $user['id_card'],
                'order_number' => $order_number,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
            return returnJson(200, '领取成功');
        }
    }

    public function journalism_list(Request $request)
    {
        $journalism = Db::name('api_journalism')
            ->field('*')
            ->where('delete_time IS NULL')
            ->when($request->param('title'), function ($query, $data) {
                return $query
                    ->where('title', 'like', "%{$data}%");
            })
            ->order('sort desc');

        $count = (clone $journalism)
            ->count();

        $journalism = $journalism
            ->page((int)$request->param('page', 1), (int)$request->param('page_size', 10))
            ->select();

        return returnJson(200, '成功', [
            'data' => $journalism,
            'count' => $count
        ]);
    }

    public function sign_in(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        if (cache('token_sign_' . $user['id'])) {
            cache('token_sign_' . $user['id'], null);
            return returnJson(400, '您的提交太频繁');
        } else {
            $__token__ = 'token_sign_' . md5(time() . rand(100000, 999999));
            cache('token_sign_' . $user['id'], $__token__);
        }
        if (checkClicks($user['id'])) {
            return returnJson(400, '您的提交太频繁');
        }
        $sign = Db::name('api_yuebao_log')->where('type', 2)->where('user_id', $user['id'])->order('income_time desc')->find();
        if ($sign) {
            if (strtotime(date('Y-m-d')) <= $sign['income_time'] && strtotime(date('Y-m-d 23:59:59')) >= $sign['income_time']) {
                return returnJson(400, '今天已经签到啦!');
            }
        }
        $config = config('web');
        $integral = $config['sign_in_reward'];
        // $ret = Db::name('api_yuebao')->where('user_id', $user['id'])->count();
        // if($ret){
        //     Db::name('api_yuebao')->where('user_id', $user['id'])->update([
        //         'amount' => Db::raw('amount+'.$integral)
        //     ]);
        // } else {
        //     Db::name('api_yuebao')->insert([
        //         'user_id' => $user['id'],
        //         'amount' => $integral, 
        //         'addtime' => time()
        //     ]);
        // }
        // $ret = Db::name('api_user')->where('id', $user['id'])->inc('withdraw_price', (float) $integral)->update();
        $ret = Db::name('api_user')->where('id', $user['id'])->inc('cash_price', (float) $integral)->update();
        $result = false;
        if ($ret) {
            Db::name('api_fund_detail')->insert([
                'data_type' => 30,
                'user_id' => $user['id'],
                'price' => $integral,
                'before' => $user['cash_price'],
                'after' => $user['cash_price'] + $integral,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s')
            ]);
        }
        $result = Db::name('api_yuebao_log')->insert([
            'type' => 2,
            'user_id' => $user['id'],
            'income' => $integral,
            'income_time' => time()
        ]);
        if (!$result) {
            return returnJson(400, '签到失败,请重试');
        }
        cache('token_sign_' . $user['id'], null);
        return returnJson(200, '签到成功');
    }

    // 签到状态
    public function sign_status()
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $week = (int)date('w');
        if ($week > 0) {
            $start = strtotime(date('Y-m-d', strtotime('-' . ($week - 1) . ' day', time())));
        } else {
            $start = strtotime(date('Y-m-d', strtotime('-6 day', time())));
        }
        $data = [];
        for ($i = 0; $i < 7; $i++) {
            $obj = [];
            $obj['date'] = date('Y-m-d', strtotime('+' . ($i) . ' day', $start));
            $obj['week'] = $i + 1;

            $count = Db::name('api_yuebao_log')
                ->where('user_id', $user['id'])
                ->where('income_time', '>=', strtotime(date('Y-m-d', strtotime('+' . ($i) . ' day', $start))))
                ->where('income_time', '<=', strtotime(date('Y-m-d 23:59:59', strtotime('+' . ($i) . ' day', $start))))
                ->where('type', 2)
                ->count();
            $obj['is_sign'] = $count > 0 ? 1 : 0;
            $data[] = $obj;
        }
        return returnJson(200, '签到状态', $data);
    }

    public function exchange(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $__token__ = cache('token_transfer_' . $user['id']);
        if ($__token__ != $request->param('__token__')) {
            cache('token' . $user['id'], null);
            return returnJson(400, '您的提交太频繁');
        } else {
            cache('token' . $user['id'], null);
        }
        if (strtotime(date('2023-1-29')) > time() || time() > strtotime(date('2023-2-5 23:59:59'))) {
            return returnJson(400, '兑换时间为正月初八至正月十五');
        }

        $config = config('web');
        $count1 = Db::name('api_clock_in')->where('user_id', $user['id'])->where('date', '2023-1-22')->count();
        $count2 = Db::name('api_clock_in')->where('user_id', $user['id'])->where('date', '2023-1-23')->count();
        $count3 = Db::name('api_clock_in')->where('user_id', $user['id'])->where('date', '2023-1-24')->count();
        $count4 = Db::name('api_clock_in')->where('user_id', $user['id'])->where('date', '2023-1-25')->count();
        $count5 = Db::name('api_clock_in')->where('user_id', $user['id'])->where('date', '2023-1-26')->count();
        $count6 = Db::name('api_clock_in')->where('user_id', $user['id'])->where('date', '2023-1-27')->count();
        $count7 = Db::name('api_clock_in')->where('user_id', $user['id'])->where('date', '2023-1-28')->count();
        $count = Db::name('api_subscribe')->where('user_id', $user['id'])->where('product_id', $config['product_set'])->where('is_clock_in', 1)->count();
        if ($count1 && $count2 && $count3 && $count4 && $count5 && $count6 && $count7 && !$count) {
            $product = Db::name('api_product')->where('id', $config['product_set'])->find();
            $estimated_total_revenue = round($product['price'] * $product['proceeds'] / 100, 1);
            $end_time = time() + (86400 * $product['cycle']);
            $order_number = getRandChar(18);
            $res = Db::name('api_subscribe')->insert([
                'user_id' => $user['id'],
                'product_id' => $config['product_set'],
                'is_clock_in' => 1,
                'product_type' => $product['product_type'],
                'method' => $product['method'],
                'name' => $product['name'],
                'price' => $product['price'],
                'cycle' => $product['cycle'],
                'image' => $product['image'],
                'status' => 1,
                'proceeds' => $product['proceeds'],
                'end_time' => date('Y-m-d H:i:s', $end_time),
                'estimated_total_revenue' => $estimated_total_revenue, // 预计收入
                'cash_back_amount_per_day' => round($product['proceeds'] * $product['price'] / 100, 1),
                'username' => $user['username'],
                'id_card' => $user['id_card'],
                'order_number' => $order_number,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
            return returnJson(200, '兑换成功');
        } else if ($count) {
            return returnJson(400, '已经兑换');
        } else {
            return returnJson(400, '还未集齐');
        }
    }

    // 打卡
    public function clock_in(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $__token__ = cache('token_transfer_' . $user['id']);
        if ($__token__ != $request->param('__token__')) {
            cache('token' . $user['id'], null);
            return returnJson(400, '您的提交太频繁');
        } else {
            cache('token' . $user['id'], null);
        }
        $hour = intval(date('H'));
        $m = intval(date('i'));
        $s = intval(date('s'));
        if (($hour == 10 && $m > 0 && $s > 0) || $hour > 10 || $hour < 8) {
            return returnJson(400, '打卡必须在上午8-10点');
        }
        if (strtotime(date('Y-m-d')) < strtotime('2023-1-22') || strtotime(date('Y-m-d')) > strtotime('2023-1-28')) {
            return returnJson(400, '超出打卡时间');
        }
        $date = date('Y-m-d');
        $count = Db::name('api_clock_in')->where('user_id', $user['id'])->where('date', $date)->count();
        if (!$count) {
            Db::name('api_clock_in')->insert([
                'user_id' => $user['id'],
                'date' => $date,
                'create_time' => time()
            ]);
            return returnJson(200, '打卡成功');
        } else {
            return returnJson(400, '一天只能打卡一次');
        }
    }

    public function clock_in_status()
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $obj1['date'] = '2023-1-22';
        $count1 = Db::name('api_clock_in')
            ->where('user_id', $user['id'])
            ->where('date', '2023-1-22')
            ->count();
        $obj1['is_clock_in'] = $count1 > 0 ? 1 : 0;
        $data[] = $obj1;

        $obj2['date'] = '2023-1-23';
        $count2 = Db::name('api_clock_in')
            ->where('user_id', $user['id'])
            ->where('date', '2023-1-23')
            ->count();
        $obj2['is_clock_in'] = $count2 > 0 ? 1 : 0;
        $data[] = $obj2;

        $obj3['date'] = '2023-1-24';
        $count3 = Db::name('api_clock_in')
            ->where('user_id', $user['id'])
            ->where('date', '2023-1-24')
            ->count();
        $obj3['is_clock_in'] = $count3 > 0 ? 1 : 0;
        $data[] = $obj3;

        $obj4['date'] = '2023-1-25';
        $count4 = Db::name('api_clock_in')
            ->where('user_id', $user['id'])
            ->where('date', '2023-1-25')
            ->count();
        $obj4['is_clock_in'] = $count4 > 0 ? 1 : 0;
        $data[] = $obj4;

        $obj5['date'] = '2023-1-26';
        $count5 = Db::name('api_clock_in')
            ->where('user_id', $user['id'])
            ->where('date', '2023-1-26')
            ->count();
        $obj5['is_clock_in'] = $count5 > 0 ? 1 : 0;
        $data[] = $obj5;

        $obj6['date'] = '2023-1-27';
        $count6 = Db::name('api_clock_in')
            ->where('user_id', $user['id'])
            ->where('date', '2023-1-27')
            ->count();
        $obj6['is_clock_in'] = $count6 > 0 ? 1 : 0;
        $data[] = $obj6;

        $obj7['date'] = '2023-1-28';
        $count7 = Db::name('api_clock_in')
            ->where('user_id', $user['id'])
            ->where('date', '2023-1-28')
            ->count();
        $obj7['is_clock_in'] = $count7 > 0 ? 1 : 0;
        $data[] = $obj7;

        return returnJson(200, '打卡状态', $data);
    }

    // 补卡
    public function clock_supply(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $__token__ = cache('token_transfer_' . $user['id']);
        if ($__token__ != $request->param('__token__')) {
            cache('token' . $user['id'], null);
            return returnJson(400, '您的提交太频繁');
        } else {
            cache('token' . $user['id'], null);
        }
        $date = $request->param('date');

        if (strtotime($date) < strtotime('2023-1-22') || strtotime($date) > strtotime('2023-1-28')) {
            return returnJson(400, '超出打卡时间');
        }
        if (time() < strtotime('2023-1-22 10:00:00') || time() > strtotime('2023-2-5 23:58:59')) {
            return returnJson(400, '超出补卡时间');
        }

        $config = config('web');
        if ($user['price'] < $config['sign_amount']) {
            return returnJson(400, '余额不足');
        }
        $count = Db::name('api_clock_in')->where('user_id', $user['id'])->where('date', $date)->count();
        if (!$count) {
            Db::startTrans();
            try {
                Db::name('api_clock_in')->insert([
                    'user_id' => $user['id'],
                    'date' => $date,
                    'create_time' => time()
                ]);
                Db::name('api_user')->where('id', $user['id'])->update([
                    'price' => Db::raw('price-' . $config['sign_amount'])
                ]);
                $result = Db::name('api_fund_detail')->insert([
                    'data_type' => 19,
                    'user_id' => $user['id'],
                    'price' => $config['sign_amount'],
                    'status' => 1,
                    'remarks' => '',
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
                Db::commit();
                return returnJson(200, '点亮成功');
            } catch (\Exception $e) {
                Db::rollback();
                return returnJson(400, '处理异常');
            }
        } else {
            return returnJson(400, '已点亮无需购买点亮');
        }
    }

    public function journalism_get(Request $request)
    {
        $journalism = Db::name('api_journalism')
            ->field('*')
            ->where('delete_time IS NULL')
            ->where('id', $request->param('id'))
            ->find();
        if (!$journalism) {
            return returnJson(400, '未找到当前新闻');
        }
        return returnJson(200, '成功', $journalism);
    }


    //产品详情
    public function product_detail(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $product_id = $request->param('product_id');
        if (empty($product_id)) {
            return returnJson(400, '参数缺失');
        }
        $product = Db::name('api_product')->where('id', $product_id)->find();
        if ($product['countdown'] <= date('Y-m-d H:i:s')) {
            $product['countdown'] = 0;
        }
        $benefit_amount = Db::name('api_benefits')
            ->where([
                ['user_id', '=', $user['id']],
                ['status', '=', 0],
                ['type', '=', 0]
            ])->sum('amount');
        $product['benefit_amount'] = $benefit_amount;
        $product['isPrice'] = ($product['product_type'] == 3 || $product['sum_type'] == 3) ? true : false;
        $__token__ = md5(time() . rand(100000, 999999));
        cache('token_pay_' . $user['id'], $__token__);
        return returnJson(200, '成功', [
            'data' => $product,
            '__token__' => $__token__
        ]);
    }


    public function publicwelfare_list(Request $request)
    {
        $product = Db::name('api_user_publicwelfare')
            ->field('*')
            ->where('delete_time IS NULL')
            ->order('id desc');

        $count = (clone $product)
            ->count();

        $product = $product
            ->page((int)$request->param('page', 1), (int)$request->param('page_size', 10))
            ->select();

        return returnJson(200, '成功', [
            'data' => $product,
            'count' => $count
        ]);
    }



    public function product_list(Request $request)
    {
        $product = Db::name('api_product')
            ->field('*')
            ->where('delete_time IS NULL')
            ->where('product_status', 1)
            //兑换券版本
            ->when($request->param('product_type'), function ($query, $data) {
                if ($data == 1) {
                    return $query
                        ->where('product_type', 'in', [1, 2]);
                } else {
                    return $query
                        ->where('product_type', $data == 2 ? 0 : $data);
                }
            })
            // ->when($request->param('product_type'), function ($query, $data) {
            //     if ($data == 1) {
            //         return $query
            //             ->where('product_type', 'in', 1);
            //     } else {
            //         return $query
            //             ->where('product_type', $data);
            //     }
            // })
            ->when($request->param('product_id'), function ($query, $data) {
                if ($data > 0) {
                    return $query->where('id', '=', $data);
                }
            })
            ->order('sort desc')->order('product_type desc');

        // halt($product->fetchSql(true)->select());
        $count = (clone $product)
            ->count();
        $web = config('web');
        $tab = [0];
        if ($web['insurance_open']) {
            $tab[] = 1;
        }
        $product = $product
            ->page((int)$request->param('page', 1), (int)$request->param('page_size', 10))
            ->select()->toArray();

        return returnJson(200, '成功', [
            'data' => $product,
            'count' => $count,
            'tab' => $tab,
        ]);
    }




    //功能：计算两个时间戳之间相差的日时分秒
    //$begin_time 开始时间戳
    //$end_time 结束时间戳
    function timediff($begin_time, $end_time)
    {
        if ($begin_time < $end_time) {
            $starttime = $begin_time;
            $endtime = $end_time;
        } else {
            $starttime = $end_time;
            $endtime = $begin_time;
        }
        //计算天数
        $timediff = $endtime - $starttime;
        $days = intval($timediff / 86400);
        //计算小时数
        $remain = $timediff % 86400;
        $hours = intval($remain / 3600);
        //计算分钟数
        $remain = $remain % 3600;
        $mins = intval($remain / 60);
        //计算秒数
        $secs = $remain % 60;
        $res = array("day" => $days, "hour" => $hours, "min" => $mins, "sec" => $secs);
        return $res;
    }

    public function brief_introduction()
    {
        return returnJson(200, '成功', str_replace("<img src=\"/upload/default/", "<img src=\"https://yunde.jrytc.cn/upload/default/", config('web')['brief_introduction']));
    }

    public function get_rich_rewards()
    {
        return returnJson(200, '成功', str_replace("<img src=\"/upload/default/", "<img src=\"https://yunde.jrytc.cn/upload/default/", config('web')['get_rich_rewards']));
    }

    public function product_prospect()
    {
        return returnJson(200, '成功', str_replace("<img src=\"/upload/default/", "<img src=\"https://yunde.jrytc.cn/upload/default/", config('web')['product_prospect']));
    }

    public function culture()
    {
        $data = [
            'culture' => str_replace("<img src=\"/upload/default/", "<img src=\"https://yunde.jrytc.cn/upload/default/", config('web')['culture']),
            'corporate_culture_pictures1' => config('web')['corporate_culture_pictures1'],
            'corporate_culture_pictures2' => config('web')['corporate_culture_pictures2'],
            'corporate_culture_pictures3' => config('web')['corporate_culture_pictures3'],
            'corporate_culture_pictures4' => config('web')['corporate_culture_pictures4'],
            'corporate_culture_pictures5' => config('web')['corporate_culture_pictures5'],
        ];
        return returnJson(200, '成功', $data);
    }

    public function legal_declaration()
    {
        return returnJson(200, '成功', str_replace("<img src=\"/upload/default/", "<img src=\"https://yunde.jrytc.cn/upload/default/", config('web')['legal_declaration']));
    }

    public function equity_certificate(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $api_subscribe = Db::name('api_subscribe')->where('status', '>', 0)->where('user_id', $user['id'])->order('create_time', 'asc')->find();
        $product_name = '';
        $price = '';
        if ($api_subscribe) {
            $product_name = $api_subscribe['price'];
        } else {
            return returnJson(200, '成功', '');
        }

        $dst_path = './' . config('web')['equity_certificate'];
        $dst = imagecreatefromstring(file_get_contents($dst_path));
        $black = imagecolorallocate($dst, 0x00, 0x00, 0x00); //字体颜色
        putenv('GDFONTPATH=' . realpath('.'));  // Name the font to be used (note the lack of the .ttf extension)
        $font = 'msyh.ttf';
        imagefttext($dst, 20, 0, 145, 532, $black, $font, $user['username']);
        imagefttext($dst, 20, 0, 145, 578, $black, $font, $product_name);
        imagefttext($dst, 17, 0, 630, 1072, $black, $font, date('Y'));
        imagefttext($dst, 17, 0, 703, 1072, $black, $font, date('m'));
        imagefttext($dst, 17, 0, 745, 1072, $black, $font, date('d'));
        //输出图片
        $file_path = './equity_certificate/' . date('Y_m_d_H_i_s');
        list($dst_w, $dst_h, $dst_type) = getimagesize($dst_path);
        switch ($dst_type) {
            case 1: //GIF
                header('Content-Type: image/gif');
                $file_path .= '.gif';
                imagegif($dst, $file_path);
                break;
            case 2: //JPG
                header('Content-Type: image/jpeg');
                $file_path .= '.jpeg';
                imagejpeg($dst, $file_path);
                break;
            case 3: //PNG
                header('Content-Type: image/png');
                $file_path .= '.png';
                imagepng($dst, $file_path);
                break;
            default:
                break;
        }
        imagedestroy($dst);

        //        return returnJson(200, '成功', config('web')['equity_certificate']);
        return returnJson(200, '成功', $file_path);
    }

    public function team_rewards()
    {
        return returnJson(200, '成功', config('web')['team_rewards']);
    }

    public function subscription_preview(Request $request)
    {
        $product = Db::name('api_product')
            ->where('delete_time IS NULL')
            ->where('id', $request->param('id'))
            ->find();

        if (!$product) {
            return returnJson(400, '未找到当前产品');
        }

        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        if (empty($user['username']) || empty($user['id_card'])) {
            return returnJson(302, '请先绑定身份证信息');
        }

        $dst_path = './' . config('web')['subscription_certificate'];
        $order = getRandChar(18);
        // $dst = imagecreatefromstring(file_get_contents($dst_path));
        // $black = imagecolorallocate($dst, 0x00, 0x00, 0x00);//字体颜色
        // putenv('GDFONTPATH=' . realpath('.'));  // Name the font to be used (note the lack of the .ttf extension)
        // $font = 'msyh.ttf';
        // imagefttext($dst, 19, 0, 180, 395, $black, $font, $user['username']);
        // imagefttext($dst, 19, 0, 180, 458, $black, $font, $user['id_card']);
        // imagefttext($dst, 19, 0, 180, 515, $black, $font, $product['price'] . '￥');
        // imagefttext($dst, 19, 0, 180, 575, $black, $font, $order);
        // imagefttext($dst, 17, 0, 250, 727, $black, $font, date('Y'));
        // imagefttext($dst, 17, 0, 330, 727, $black, $font, date('m'));
        // imagefttext($dst, 17, 0,390, 727, $black, $font, date('d'));
        // //输出图片
        // $file_path = './subscribe/' . date('Y-m-d') . $order;
        // list($dst_w, $dst_h, $dst_type) = getimagesize($dst_path);
        // switch ($dst_type) {
        //     case 1://GIF
        //         header('Content-Type: image/gif');
        //         $file_path .= '.gif';
        //         imagegif($dst, $file_path);
        //         break;
        //     case 2://JPG
        //         header('Content-Type: image/jpeg');
        //         $file_path .= '.jpeg';
        //         imagejpeg($dst, $file_path);
        //         break;
        //     case 3://PNG
        //         header('Content-Type: image/png');
        //         $file_path .= '.png';
        //         imagepng($dst, $file_path);
        //         break;
        //     default:
        //         break;
        // }
        // imagedestroy($dst);

        return returnJson(200, '成功', [
            'file_path' => $file_path,
            'product_id' => $product['id'],
            'price' => $product['price'],
            'product_type' => $product['product_type'],
            'order_number' => $order,
        ]);
    }

    public function id_card_get(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $api_user = Db::name('api_user')->where('id', $user['id'])->find();

        return returnJson(200, '成功', [
            'username' => $user['username'],
            'sex' => isset($api_user)? $api_user['sex']: "",
            'reason' => isset($api_user)? $api_user['reason']: "",
            'id_card' => $user['id_card'],
            'id_card_front' => $user['id_card_front'],
            'id_card_back' => $user['id_card_back'],
        ]);
    }

    public function checkIdCard($value)
    {
        //校验身份证位数和出生日期部分
        $pattern = "/^\d{6}(18|19|20)?\d{2}(0[1-9]|1[012])(0[1-9]|[12]\d|3[01])\d{3}(\d|[xX])$/";
        preg_match($pattern, $value, $match);
        $result = $match ? true : '请填写正确的身份证号';
        if (!$result) {
            return '请填写正确的身份证号';
        }
        //校验前两位是否是所有省份代码
        $province_code = ['11', '12', '13', '14', '15', '21', '22', '23', '31', '32', '33', '34', '35', '36', '37', '41', '42', '43', '44', '45', '46', '50', '51', '52', '53', '54', '61', '62', '63', '64', '65', '71', '81', '82', '91'];
        if (!in_array(substr($value, 0, 2), $province_code)) {
            return '请填写正确的身份证号';
        }
        //校验身份证最后一位
        $ahead17_char = substr($value, 0, 17);
        $last_char = substr($value, -1);
        $factor = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2); // 前17位的权重
        $c = array(1, 0, 'X', 9, 8, 7, 6, 5, 4, 3, 2); //模11后的对应校验码
        $t_res = 0;
        for ($i = 0; $i < 17; $i++) {
            $t_res += intval($ahead17_char[$i]) * $factor[$i];
        }
        $calc_last_char = $c[$t_res % 11];
        if ($last_char != $calc_last_char) {
            return '请填写正确的身份证号';
        }
        return true;
    }

    public function id_card_save(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        if (!@preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9]+$/u', $request->param('username'))) {
            return returnJson(400, '非法操作');
        }
        if (!@preg_match("/^[xX0-9]+$/u", $request->param('id_card')) || strlen($request->param('id_card')) < 15) {
            return returnJson(400, '请填写正确的身份证号');
        }
        // if ($this->checkIdCard($request->param('id_card')) !== true) {
        //     return returnJson(400, '请填写正确的身份证号');
        // }
        $r = "/http[s]?:\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is";
        if (!@preg_match($r, $request->param('id_card_front'))) {
            return returnJson(400, '非法操作');
        }
        if (!@preg_match($r, $request->param('id_card_back'))) {
            return returnJson(400, '非法操作');
        }
        $count = Db::name('api_user')->where('id_card', $request->param('id_card'))->where('id', '<>', $user['id'])->count();
        if ($count > 0) {
            return returnJson(400, '用户信息已经认证');
        }
        if ($user['status'] == 3) {
            return returnJson(400, '您的认证信息正在审核中');
        }
        if ($user['status'] == 2) {
            return returnJson(400, '您的账户已冻结，请联系客服');
        }
        if (empty($request->param('id_card_back')) || empty($request->param('id_card_front'))) {
            return returnJson(400, '请上传身份证照片');
        }
        if ($user['status'] == 3 ||  $user['status'] == 1) {
            return returnJson(400, '已绑定成功');
        }
        Db::startTrans();
        try {
            Db::name('api_user')
                ->where('id', $user['id'])
                ->where('delete_time IS NULL')
                ->update([
                    'username' => $request->param('username'),
                    'sex' => $request->param('sex'),
                    'id_card' => $request->param('id_card'),
                    'id_card_front' => $request->param('id_card_front'),
                    'id_card_back' => $request->param('id_card_back'),
                    'status'        => 3
                ]);
            // if($user['parent_user_id']){
            //     $this->set_offline_auths($user['parent_user_id']);
            // }
            Db::commit();
            cache('mobile' . $request->param('mobile'), null);
            return returnJson(200, '成功');
        } catch (\Exception $e) {
            Db::rollback();
            cache('mobile' . $request->param('mobile'), null);
            return returnJson(400, '注册失败' . $e->getMessage() . $e->getLine());
        }
        if (!$user) {
            return returnJson(400, '保存失败');
        }
    }

    public function set_offline_auths($user_id)
    {
        $parent_user = Db::name('api_user')->where('id', $user_id)->find();
        Db::name('api_user')->where('id', $user_id)->update([
            'offline_auths' => Db::raw('offline_auths+1')
        ]);
        $date = time();
        if (date('d') >= 10) {
            $month_start = strtotime(date("Y-m-10 00:00:00"));
            $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 months', strtotime(date('Y-m-1')))));
        } else {
            $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month', strtotime(date('Y-m-1')))));
            $month_end = strtotime(date("Y-m-9 23:59:59"));
        }
        Db::name('api_monthlog')->insert([
            'user_id' => $user_id,
            'from_user_id' => $this->user['id'],
            'month_start' => $month_start,
            'month_end' => $month_end,
            'log_type' => 3,
            'addtime' => time()
        ]);
        $sign_count = Db::name('api_monthlog')->where('user_id', $user_id)->where('addtime', '>=', strtotime(date('Y-m-d')))->where('addtime', '<', strtotime(date('Y-m-d 23:59:59')))->count();
        if ($sign_count == 2) {
            Db::name('api_check_time')->insert([
                'user_id' => $user_id,
                'stype' => 2,
                'create_time' => time(),
                'integral' => 20
            ]);
        }
        if ($sign_count == 7) {
            Db::name('api_check_time')->insert([
                'user_id' => $user_id,
                'stype' => 2,
                'create_time' => time(),
                'integral' => 50
            ]);
        }
        if ($sign_count == 17) {
            Db::name('api_check_time')->insert([
                'user_id' => $user_id,
                'stype' => 2,
                'create_time' => time(),
                'integral' => 100
            ]);
        }
        if ($parent_user['level'] > 0) {
            $count = Db::name('api_task')->where('user_id', $user_id)->where('month_start', '>=', $month_start)->where('month_end', '<=', $month_end)->count();
            if (!$count) {
                Db::name('api_task')->insert([
                    'user_id' => $user_id,
                    'month_start' => $month_start,
                    'month_end' => $month_end,
                    'offline_auths' => 1
                ]);
            } else {
                Db::name('api_task')->where('user_id', $user_id)->where('month_start', '>=', $month_start)->where('month_end', '<=', $month_end)->update([
                    'user_id' => $user_id,
                    'offline_auths' => Db::raw('offline_auths+1')
                ]);
            }
        }
        $parent_user_id = Db::name('api_user')->where('id', $parent_user['parent_user_id'])->value('id');
        if ($parent_user_id) {
            $this->set_offline_auths($parent_user_id);
        }
    }

    public function account_list(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $account_list = Db::name('api_account')
            ->where('delete_time IS NULL')
            ->where('user_id', $user['id'])
            ->order('is_default asc')
            ->order('type asc');

        $count = (clone $account_list)
            ->count();

        $account_list = $account_list
            ->page((int)$request->param('page', 1), (int)$request->param('page_size', 10))
            ->select();
        $__token__ = md5(time() . rand(100000, 999999));
        cache('token' . $user['id'], $__token__);
        return returnJson(200, '成功', [
            'data' => $account_list,
            'count' => $count,
            '__token__' => $__token__
        ]);
    }

    public function account_get(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $result = Db::name('api_account')
            ->where('user_id', $user['id'])
            ->where('delete_time IS NULL')
            ->find();
        $__token__ = md5(time() . rand(100000, 999999));
        cache('token' . $user['id'], $__token__);
        $result['__token__'] = $__token__;

        return returnJson(200, '成功', $result);
    }

    public function account_save(Request $request)
    {
        $type = $request->param('type');
        if (!@preg_match("/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u", $request->param('account_name'))) {
            return returnJson(400, '非法操作');
        }
        if (!@preg_match("/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u", $request->param('account_number'))) {
            return returnJson(400, '非法操作');
        }
        if (!$type) {
            if (!@preg_match("/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u", $request->param('name_of_deposit_bank'))) {
                return returnJson(400, '非法操作');
            }
        }
        if (empty($request->param('is_default'))) {
            return returnJson(400, '缺少参数');
        }
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        if ($request->param('is_default') == 1) {
            Db::name('api_account')
                ->where('user_id', $user['id'])
                ->where('delete_time IS NULL')
                ->update([
                    'is_default' => 1,
                    'update_time' => date('Y-m-d H:i:s')
                ]);
        }

        $isset = Db::name('api_account')
            ->where('user_id', $user['id'])
            ->where('type', $type)
            ->where('delete_time IS NULL')
            ->when($request->param('id'), function ($query, $data) {
                return $query
                    ->where('id', '<>', $data);
            })
            // ->where('account_name',$request->param('account_name'))
            // ->where('account_number',$request->param('account_number'))
            // ->where('name_of_deposit_bank',$request->param('name_of_deposit_bank'))
            ->count();
        if ($isset) {
            return returnJson(400, '每种支付方式只能添加一个！');
        }

        if (!empty($request->param('id'))) {
            $result = true;
            // $result = Db::name('api_account')
            //     ->where('id', $request->param('id'))
            //     ->where('delete_time IS NULL')
            //     ->where('user_id', $user['id'])
            //     ->update([
            //         'account_name' => $request->param('account_name'),
            //         'account_number' => $request->param('account_number'),
            //         'name_of_deposit_bank' => $request->param('name_of_deposit_bank'),
            //         'update_time' => date('Y-m-d H:i:s'),
            //         'is_default' => $request->param('is_default'),
            //     ]);
        } else {
            $result = Db::name('api_account')
                ->insert([
                    'type' => $type,
                    'user_id' => $user['id'],
                    'account_name' => $request->param('account_name'),
                    'account_number' => $request->param('account_number'),
                    'name_of_deposit_bank' => $request->param('name_of_deposit_bank'),
                    'create_time' => date('Y-m-d H:i:s'),
                    'is_default' => $request->param('is_default'),
                ]);
        }
        if (!$result) {
            return returnJson(400, '保存失败');
        }

        return returnJson(200, '成功');
    }

    public function account_del(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $result = Db::name('api_account')
            ->where('id', $request->param('id'))
            ->where('user_id', $user['id'])
            ->where('delete_time IS NULL')
            ->update([
                'delete_time' => date('Y-m-d H:i:s')
            ]);

        if (!$result) {
            return returnJson(400, '删除失败');
        }

        return returnJson(200, '删除成功');
    }

    public static function getServerIp()
    {
        if (!empty($_SERVER['SERVER_ADDR'])) {
            return $_SERVER['SERVER_ADDR'];
        }

        return gethostbyname($_SERVER['HOSTNAME']);
    }

    public function curl_pay()
    {
        $id = $this->request->param('id');
        $subscription = Db::name('api_subscribe')
            ->where('status', 0)
            ->where('id', $id)
            ->find();
        if (!$subscription) {
            return returnJson(400, '未找到对应认购记录');
        }
        $notify_url = 'https://' . $_SERVER['HTTP_HOST'] . '/api.php/login/pay_notify';
        $back_url = 'https://' . $_SERVER['HTTP_HOST'] . '/#/pages/mine/index';
        $price = $subscription['price'] - $subscription['deduction_amount_price'];
        if ($this->request->param('type') == "1") {
            $thoroughfare = 1109;
        } else {
            $thoroughfare = 1003;
        }

        $url = 'https://api.longxiang886.xyz/gateway/index/checkpoint';

        $data = [
            'account_id' => '10050',
            "content_type" => "json",
            "thoroughfare" => $thoroughfare,
            'out_trade_no' => $subscription['order_number'],
            'timestamp' => time(),
            'ip'  => $this->request->ip(),
            'callback_url' => $notify_url,
            'success_url' => $back_url,
            "error_url" => $back_url,
            'amount' => number_format($price, 2, '.', '')
        ];
        ksort($data);
        $sign = '';
        foreach ($data as $k => $arv) {
            $sign .= $k . "=" . $arv . '&';
        }
        $sign = $sign . "key=E3C0FF73BF8AFB";
        $sign = strtolower(md5($sign));
        $data['sign'] = $sign;
        $headers = array('Content-Type: application/x-www-form-urlencoded');
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($curl); // 执行操作
        curl_close($curl);
        return returnJson(200, '成功', json_decode($result, true));
        // $curl = curl_init();
        // curl_setopt($curl, CURLOPT_URL, $url);
        // curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        // if (!empty($data)) {
        //     curl_setopt($curl, CURLOPT_POST, 1);
        //     curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        // }
        // curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // $output = curl_exec($curl);
        // print_r($output); exit;
        // $output = json_decode($output, true);
        // if(empty($output['status'])){
        //     return returnJson(200, '成功', $output['data']);
        // } else {
        //     return returnJson(210, '暂时无法支付', $output);
        // }
    }

    public function subscription_submit(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            Db::rollback();
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $user = Db::name('api_user')->where('id', $user['id'])->find();
        $product = Db::name('api_product')
            ->where('id', $request->param('product_id'))
            ->where('delete_time IS NULL')
            ->find();
        if (!$product) {
            Db::rollback();
            return returnJson(400, '未找到当前产品');
        }
        Db::startTrans();
        try {
            if (($user['price'] - $product['price']) < 0) {
                Db::rollback();

                return returnJson(212, '账户余额不足');
            } else {
                $price = $product['price'];
                $result = Db::name('api_user')->where('id', $user['id'])->dec('price', (float)$price)->update();
            }
            if ($product['product_type'] == 2 && $product['method'] == 1) {
                $end_time = time() + (86400 * $product['cycle']);
            } else if ($product['product_type'] == 2 && $product['method'] == 3) {
                $end_time = time() + ($product['cycle'] * 60);
            } else {
                Db::rollback();
                return returnJson(400, '非法操作');
            }
            $insert_array = [
                'user_id' => $user['id'],
                'product_id' => $product['id'],
                'product_type' => $product['product_type'],
                'method'    => $product['method'],
                'name' => $product['name'],
                'image' => $product['image'],
                'price' => $product['price'],
                'cycle' => $product['cycle'],
                'end_time' => date('Y-m-d H:i:s', $end_time),
                'proceeds' => $product['proceeds'],
                'status' => 1,
                'estimated_total_revenue' => 0, // 预计收入
                'amount_of_income_received' => 0, // 已收益金额
                'username' => $user['username'],
                'id_card' => $user['id_card'],
                'order_number' => getRandChar(18),
                'create_time' => date('Y-m-d H:i:s'),
            ];
            $id = Db::name('api_subscription')->insertGetId($insert_array);
            $parent_user_id = Db::name('api_user')->where('id', $user['id'])->value('parent_user_id');
            $pcount = Db::name('api_fund_detail')->where('user_id', $parent_user_id)->where('from_user_id', $user['id'])->where('data_type', 15)->count();
            if (!$pcount) {
                Db::name('api_fund_detail')->insert([
                    'data_type' => 15,
                    'user_id'   => $parent_user_id,
                    'from_user_id' => $user['id'],
                    'price'     => 10,
                    'status'    => 1,
                    'remarks'   => '致富奖励10元',
                    'create_time' => date('Y-m-d H:i:s')
                ]);
                Db::name('api_user')->where('id', $parent_user_id)->inc('get_rich_balance', 10)->update();
            }
            Db::commit();
            return returnJson(200, '尊贵的联合石化会员，您已投入高效致富产品成功，注意查看产品分钟到期时间等待本金和分红一起返回至高效致富产品余额，联系致富产品客服提现！');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '认购失败!' . $e->getMessage() . $e->getLine());
        }
    }

    public function pay(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            Db::rollback();
            return returnJson(301, '未找到当前用户请重新登录');
        }
        // $vid = !empty($request->param('vid')) ? $request->param('vid') : 0;
        $vids = $request->param('vids', '');
        $auto = $request->param('auto', "");
        $pay_password = $request->param('passwd');
        $__token__ = cache('token_pay_' . $user['id']);
        if (!empty($request->param('__token__')) && $__token__ != $request->param('__token__')) {
            cache('token_pay_' . $user['id'], null);
            // return returnJson(400, '请重新加载后再次购买');
        } else {
            cache('token_pay_' . $user['id'], null);
        }
        if (!$pay_password) {
            return returnJson(400, '请输入资金密码');
        }
        //查询指定用户 没有限制购买金额 并且还未使用的代金券
        // if ($benefits && $benefits['invalidtime'] < time()) {
        //     return returnJson(400, '代金券已失效');
        // }
        $user = Db::name('api_user')->where('id', $user['id'])->find();
        if ($user['status'] != 1) {
            return returnJson(400, '未通过实名认证不能购买产品');
        }
        if (!$user['payword']) {
            return returnJson(400, '请先设置资金密码');
        }
        if (md5(md5($pay_password)) != $user['payword']) {
            return returnJson(400, '资金密码错误，请重新输入！');
        }

        $product = Db::name('api_product')
            ->where('id', $request->param('product_id'))
            ->where('delete_time IS NULL')
            ->find();
        if (!$product || $request->param('amount') != $product['price']) {
            if (!$vids && $auto == "false") {
                return returnJson(400, '参数缺失或参数错误');
            }
        }
        // if($request->param('product_id') == 36){
        //     $count = Db::name('api_subscribe')->where('user_id', $user['id'])->where('product_id', 36)->count();
        //     if($count){
        //         return returnJson(400, '该产品限购一次');
        //     }
        // }
        $dikouMaxPrice = (float) $product['price'] * $product['deduct_rate'];
        if ($auto == "false") {
            $benefits = !$vids ? [] : Db::name('api_benefits')->where('user_id', $user['id'])->whereIn('id', explode(",", $vids))->where('status', 0)->where('type', 0)->field('id,amount')->select()->toArray();
        } else {
            $autoBenefits = Db::name('api_benefits')
                ->where([
                    ['user_id', '=', $user['id']],
                    ['status', '=', 0],
                    ['type', '=', 0]
                ])
                ->field('id,amount')
                ->order('amount asc,id asc')->select()->toArray();
            $total = 0;
            $benefits = [];
            foreach ($autoBenefits as $v) {
                $total += $v['amount'];
                $benefits[] = $v;
                if ($total >= $dikouMaxPrice) {
                    break;
                }
            }
        }
        if ($product['product_type']) {
            if ($product['product_type'] == 3) {
                $pensionInfo = Db::name('api_user_pension')
                    ->where('user_id', $user['id'])
                    ->where('status', 1)
                    ->find();
                if (!$pensionInfo) {
                    return returnJson(400, '参数缺失或参数错误2');
                }
                if ($pensionInfo['pay_status']) {
                    return returnJson(400, '抱歉，请勿重复缴纳！');
                }
            }
            if ($product['daynum']) {
                $totalDayNum = Db::name('api_subscribe')->where('product_id', $product['id'])->whereDay('create_time')->count();
                if ($totalDayNum >= $product['daynum']) {
                    return returnJson(400, "抱歉，当前产品今日已售完，请改天再来！");
                }
            }

            if (in_array($product['id'], ApiProduct::get_active_ids())) {
                $payactivecount = Db::name('api_subscribe')->alias('as')
                    ->leftJoin('api_product ap', 'ap.id=as.product_id')
                    ->where('as.user_id', $user['id'])
                    // ->whereIn('price', [0, 200])
                    ->where('ap.sum_type', 1)
                    ->count();
                if ($payactivecount) {
                    return returnJson(400, '抱歉，当前产品只适合未激活的新用户！');
                }
            } else {
                $paycount = Db::name('api_subscribe')->where('user_id', $user['id'])->where('product_id', $product['id'])->count();
                if ($product['quota'] > 0 && $paycount >= $product['quota']) {
                    return returnJson(400, '该产品限购' . $product['quota'] . '次');
                }
            }
            // if ($benefits) {
            //     if ($benefits['product_id'] != 0 && $benefits['product_id'] != $product['id']) {
            //         return returnJson(400, '非法操作');
            //     }
            // }
            if ($product['product_status'] != 1) {
                return returnJson(400, '当前产品已售罄!');
            }
            if ($product['countdown'] > date('Y-m-d H:i:s', time())) {
                return returnJson(400, '当前产品倒计时结束前不可购买!');
            }
            $deductPrcie = 0;
            if ($benefits) {
                if (!empty($benefits)) {
                    $deductPrcie = array_sum(array_column($benefits, 'amount'));
                }
                $deductPrcie = min($deductPrcie, $dikouMaxPrice);
                if ($request->param('amount') < ($product['minimum_investment'] - $deductPrcie)) {
                    return returnJson(212, '认购金额不能低于产品最低投资金额');
                }
            } else {
                if ($request->param('amount') < $product['minimum_investment']) {
                    return returnJson(212, '认购金额不能低于产品最低投资金额');
                }
            }
            if ($request->param('amount') > $product['highest_investment']) {
                return returnJson(212, '认购金额不能高于产品最高投资金额');
            }
            //短期产品和周期(抵扣)产品
            $benefitsTotal = 0;
            if (in_array($product['timeline_type'], [1, 5]) && $product['voucher_id']) {
                $benefitsTotal = Db::name('api_benefits')->where('user_id', $user['id'])->where('voucher_id', $product['voucher_id'])->where('status', 0)->where('type', 1)->count();
                $voucherPrice = Db::name('api_voucher')->where('id', $product['voucher_id'])->value('amount');
                $buyPrice = $product['price'];
                $num = (int) ceil($buyPrice / $voucherPrice);
                if ($benefitsTotal < $num) {
                    return returnJson(212, "购买产品需要消耗{$num}张资格券");
                }
            }
        }
        // if($product['sum_type']==3 || $product['product_type']==3){
        //     if ((($user['price']) - $request->param('amount')) < 0) {
        //         return returnJson(212, '可用余额不足');
        //     }
        // }else{
        //     if ((($user['price'] + $user['cash_price']) - $request->param('amount')) < 0) {
        //         return returnJson(212, '账户余额不足');
        //     }
        // }
        if ((($user['price']) - $request->param('amount')) < 0) {
            return returnJson(212, '可用余额不足');
        }
        Db::startTrans();
        try {
            //预计收入 认购金额*收益百分比*周期天数 保留一位小数
            $estimated_total_revenue = round($request->param('amount') * $product['proceeds'] / 100, 1);
            $is_curl_pay = 0;
            $curl_pay_price = 0;
            $price = $product['price'];
            $amount = $request->param('amount');
            $order_number = getRandChar(18);
            if ($benefits) {
                $abids = array_column($benefits, 'id');
                $daijinPrcie = array_sum(array_column($benefits, 'amount'));
                // $daijinPrcie = $daijinPrcie > $dikouMaxPrice ? $dikouMaxPrice : $daijinPrcie;
                $daijinPrcie = min($daijinPrcie, $dikouMaxPrice);
                $amount = $price - $daijinPrcie;
                Db::name('api_benefits')->whereIn('id', $abids)->update([
                    'addtime' => time(),
                    'status' => 1
                ]);
                Db::name('api_fund_detail')->insert([
                    'data_type' => 26,
                    'user_id' => $user['id'],
                    'price' => (float)$daijinPrcie,
                    'status' => 1,
                    'remarks' => "订单号:" . $order_number . '|扣除代金券:' . (float)$daijinPrcie,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            }
            if ($amount > 0) {
                if ($user['price'] >= $amount) {
                    Db::name('api_user')->where('id', $user['id'])->dec('price', (float)($amount))->update();
                } else {
                    $otherPrice = $amount - $user['price'];
                    Db::name('api_user')->where('id', $user['id'])
                        ->dec('price', (float)($user['price']))
                        ->dec('cash_price', (float)($otherPrice))
                        ->update();
                }
            }
            $end_time = time() + (86400 * ($product['timeline_type'] == 2 ? 1 : $product['cycle']));
            $insert_array = [
                'user_id' => $user['id'],
                'product_id' => $product['id'],
                'product_type' => $product['product_type'],
                'timeline_type' => $product['timeline_type'],
                'sum_type' => $product['sum_type'],
                'method'    => $product['method'],
                'name' => $product['name'],
                'image' => $product['image'],
                'price' => $product['price'],
                'cycle' => $product['cycle'],
                'end_time' => date('Y-m-d H:i:s', $end_time),
                'proceeds' => $product['proceeds'],
                'status' => 1,
                'estimated_total_revenue' => $estimated_total_revenue, // 预计收入
                'cash_back_amount_per_day' => round($product['proceeds'] * $request->param('amount') / 100, 1), // 已收益金额
                'username' => $user['username'],
                'id_card' => $user['id_card'],
                'order_number' => $order_number,
                'create_time' => date('Y-m-d H:i:s'),
            ];
            $id = Db::name('api_subscribe')->insertGetId($insert_array);
            if ($is_curl_pay) {
                Db::commit();
                $result['price'] = $curl_pay_price;
                return returnJson(210, '成功', $result);
            } else {
                $result = Db::name('api_fund_detail')->insert([
                    'data_type' => 3,
                    'user_id' => $user['id'],
                    'price' => (float)$amount,
                    'status' => 1,
                    'remarks' => '产品购买,订单号:' . $order_number,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            }
            if ($id) {
                // if (!$product['product_type']) {
                // $data = [
                //     'id' => $id,
                //     'user_id' => $user['id'],
                //     'psname' => $product['short_name'],
                //     'pname' => $product['name'],
                //     'pcode' => $product['code'],
                //     'pmanager' => $product['manager'],
                //     'name' => $user['username'],
                //     'sex' => $user['sex'] ? '女' : '男',
                //     'idcard' => $user['id_card'],
                //     'money' => (int) ($price),
                //     'money_text' => numberToChinese($price, true)
                // ];
                // $image = $this->get_insurance_image($data);
                // Db::name('api_subscribe')->where('id', $id)->update(['insurance_image' => $image]);
                // }
            }
            $config = config('web');
            if ($product['set_pension'] > 0) {
                $result = Db::name('api_user')->where('id', $user['id'])->inc('pension_price', (float)($product['set_pension']))->update();
                Db::name('api_fund_detail')->insert([
                    'data_type' => 20,
                    'user_id' => $user['id'],
                    'price' => (float)$product['set_pension'],
                    'status' => 1,
                    'remarks' => '认购赠送养老金',
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            }
            if ($product['timeline_type']) {
                switch ($product['timeline_type']) {
                    case '1':
                    case '5':
                        if ($benefitsTotal >= $num) {
                            Db::name('api_benefits')
                                ->where('user_id', $user['id'])
                                ->where('status', 0)
                                ->where('voucher_id', $product['voucher_id'])
                                ->where('type', 1)
                                ->order('id asc')
                                ->limit($num)
                                ->update(['status' => 1]);
                        }
                        Db::name('api_fund_detail')->insert([
                            'data_type' => 26,
                            'user_id' => $user['id'],
                            'price' => 0,
                            'status' => 1,
                            'remarks' => "订单号:" . $order_number . '|扣除资格券:' . $num . "张",
                            'create_time' => date('Y-m-d H:i:s'),
                        ]);
                        break;
                    case '2':
                    case '4':
                        if (!$product['voucher_id']) {
                            break;
                        }
                        $model = Db::name('api_voucher')->where('id', $product['voucher_id'])->find();
                        Db::name('api_benefits')->insert([
                            'user_id' => $user['id'],
                            'type' => 1,
                            'voucher_id' => $model['id'],
                            'amount' => $model['amount'],
                            'addtime' => strtotime($model['starttime']),
                            'invalidtime' => strtotime($model['endtime']),
                        ]);
                        Db::name('api_fund_detail')
                            ->insert([
                                'data_type' => 15,
                                'recharge_type' => 0,
                                'user_id' => $user['id'],
                                'price' => $model['amount'],
                                'node' => '',
                                'status' => 1,
                                'remarks' => $user['id'] . "：购买赠送资格券一张",
                                'create_time' => date('Y-m-d H:i:s'),
                            ]);
                        $this->set_parent_commion($user, $amount, $config, $product, $order_number);
                        break;
                    case '3':
                        if (!$product['price']) {
                            Db::commit();
                            return returnJson(200, '认购成功！');
                        }
                        if ($product['voucher_id']) {
                            $model = Db::name('api_voucher')->where('id', $product['voucher_id'])->field('id,amount,starttime,endtime')->find();
                            Db::name('api_benefits')->insert([
                                'user_id' => $user['id'],
                                'type' => 1,
                                'voucher_id' => $model['id'],
                                'amount' => $model['amount'],
                                'addtime' => strtotime($model['starttime']),
                                'invalidtime' => strtotime($model['endtime']),
                            ]);
                            Db::name('api_fund_detail')
                                ->insert([
                                    'data_type' => 15,
                                    'recharge_type' => 0,
                                    'user_id' => $user['id'],
                                    'price' => $model['amount'],
                                    'node' => '',
                                    'status' => 1,
                                    'remarks' => $user['id'] . "：购买赠送资格券一张",
                                    'create_time' => date('Y-m-d H:i:s'),
                                ]);
                            $this->set_parent_commion($user, $amount, $config, $product, $order_number);
                        } else {
                            $this->set_parent_commion($user, $amount, $config, $product, $order_number);
                        }
                        break;
                }
            } else {
                if (!$product['price']) {
                    Db::commit();
                    return returnJson(200, '认购成功！');
                }
                if ($product['sum_type'] == 3) {
                    if ($product['voucher_id']) {
                        $model = Db::name('api_voucher')->where('id', $product['voucher_id'])->field('id,amount,starttime,endtime')->find();
                        Db::name('api_benefits')->insert([
                            'user_id' => $user['id'],
                            'type' => 1,
                            'voucher_id' => $model['id'],
                            'amount' => $model['amount'],
                            'addtime' => strtotime($model['starttime']),
                            'invalidtime' => strtotime($model['endtime']),
                        ]);
                        Db::name('api_fund_detail')
                            ->insert([
                                'data_type' => 15,
                                'recharge_type' => 0,
                                'user_id' => $user['id'],
                                'price' => $model['amount'],
                                'node' => '',
                                'status' => 1,
                                'remarks' => $user['id'] . "：购买赠送福利券一张",
                                'create_time' => date('Y-m-d H:i:s'),
                            ]);
                    }
                } else {
                    if ($product['product_type'] == 3) {
                        Db::name('api_user_pension')
                            ->where('user_id', $user['id'])
                            ->where('status', 1)
                            ->update(['pay_status' => 1, 'handle_time' => date('Y-m-d H:i:s', time())]);
                    }
                    $this->set_parent_commion($user, $amount, $config, $product, $order_number);
                }
            }
            if (!$id) {
                Db::rollback();
                return returnJson(400, '处理异常');
            }
            Db::commit();
            if (!$product['product_type']) {
                return returnJson(200, '认购成功！', ['id' => $id]);
            }
            return returnJson(200, '认购成功！');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '认购失败!' . $e->getMessage() . $e->getLine());
        }
    }

    public function set_parent_commion($user, $amount, $config, $product, $order_number)
    {
        $first_parent = Db::name('api_user')->where('id', $user['id'])->value('parent_user_id');
        if ($first_parent) {
            if ($product['voucher_id']) {
                $model = Db::name('api_voucher')->where('id', $product['voucher_id'])->field('id,amount,starttime,endtime')->find();
                Db::name('api_benefits')->insert([
                    'user_id' => $first_parent,
                    'type' => 1,
                    'voucher_id' => $model['id'],
                    'amount' => $model['amount'],
                    'addtime' => strtotime($model['starttime']),
                    'invalidtime' => strtotime($model['endtime']),
                ]);
                Db::name('api_fund_detail')
                    ->insert([
                        'data_type' => 15,
                        'recharge_type' => 0,
                        'user_id' => $first_parent,
                        'price' => $model['amount'],
                        'node' => '',
                        'status' => 1,
                        'remarks' => $user['id'] . "：下级购买赠送资格券一张",
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
            }
            // Db::name('api_fund_detail')->insert([
            //     'data_type'     => 8,
            //     'user_id' => $first_parent,
            //     'price' => (float)($amount * $config['generation_reward'] / 100),
            //     'status' => 1,
            //     'remarks' => '产品购买,订单号:' . $order_number,
            //     'create_time' => date('Y-m-d H:i:s'),
            // ]);
            // Db::name('api_user')->where('id', $first_parent)->inc('withdraw_price', (float)($amount * $config['generation_reward'] / 100))->update();
            // $second_parent = Db::name('api_user')->where('id', $first_parent)->value('parent_user_id');
            // if ($second_parent) {
            //     Db::name('api_fund_detail')->insert([
            //         'data_type'     => 9,
            //         'user_id' => $second_parent,
            //         'price' => (float)($amount * $config['second-generation_rewards'] / 100),
            //         'status' => 1,
            //         'remarks' => '产品购买,订单号:' . $order_number,
            //         'create_time' => date('Y-m-d H:i:s'),
            //     ]);
            //     Db::name('api_user')->where('id', $second_parent)->inc('withdraw_price', (float)($amount * $config['second-generation_rewards'] / 100))->update();
            // }
        }
    }

    public function set_offline_buys($user_id, $amount = 0, $level = 0, $sp_count = 0, $proid = 0)
    {
        $level++;
        $parent_user = Db::name('api_user')->where('id', $user_id)->find();
        Db::name('api_user')->where('id', $user_id)->update([
            'offline_buys' => Db::raw('offline_buys+1')
        ]);
        $date = time();
        if (date('d') >= 10) {
            $month_start = strtotime(date("Y-m-10 00:00:00"));
            $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 months', strtotime(date('Y-m-1')))));
        } else {
            $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month', strtotime(date('Y-m-1')))));
            $month_end = strtotime(date("Y-m-9 23:59:59"));
        }
        $data['user_id'] = $user_id;
        if ($parent_user['level'] > 0) {
            $scount = Db::name('api_task')->where('user_id', $user_id)->where('month_start', $month_start)->where('month_end', $month_end)->count();
            if ($scount) {
                if ($sp_count) {
                    $data['recharge_buys'] = Db::raw('recharge_buys+1');
                }
                // if($amount >= 20000){
                //     $data['sub1_special'] = Db::raw('sub1_special+1');
                // }
                if ($proid == 41) {
                    $data['sub1_special'] = Db::raw('sub1_special+1');
                }
                if ($level == 1) {
                    $data['sub1_buy'] = Db::raw('sub1_buy+1');
                }
                if ($level == 2) {
                    $data['sub2_buy'] = Db::raw('sub2_buy+1');
                }
                $data['offline_buys'] = Db::raw('offline_buys+1');
                Db::name('api_task')->where('user_id', $user_id)->where('month_start', '>=', $month_start)->where('month_end', '<=', $month_end)->update($data);
            } else {
                Db::name('api_task')->insert([
                    'user_id' => $user_id,
                    'month_start' => $month_start,
                    'month_end' => $month_end,
                    'offline_buys' => 1
                ]);
            }
        }
        $parent_user_id = Db::name('api_user')->where('id', $parent_user['parent_user_id'])->value('id');
        if ($parent_user_id) {
            $this->set_offline_buys($parent_user_id, $amount, $level, $sp_count);
        }
    }

    public function set_offline_special($user_id)
    {
        $parent_user = Db::name('api_user')->where('id', $user_id)->find();
        Db::name('api_user')->where('id', $user_id)->update([
            'offline_special' => Db::raw('offline_special+1')
        ]);
        $parent_user_id = Db::name('api_user')->where('id', $parent_user['parent_user_id'])->value('id');
        if ($parent_user_id) {
            $this->set_offline_special($parent_user_id);
        }
    }

    public function transfer_in(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $__token__ = cache('token_transfer_' . $user['id']);
        if (!empty($request->param('__token__')) && $__token__ != $request->param('__token__')) {
            cache('token_transfer_' . $user['id'], null);
            return returnJson(400, '您的提交太频繁');
        } else {
            cache('token_transfer_' . $user['id'], null);
        }
        if (empty($request->param('amount')) || empty($request->param('payword'))) {
            return returnJson(400, '参数缺失');
        }
        if ($user['payword'] != md5(md5($request->param('payword')))) {
            return returnJson(400, '资金密码错误');
        }
        if ($user['price'] < $request->param('amount')) {
            return returnJson(400, '余额不足');
        }
        Db::startTrans();
        try {
            $yuebao = Db::name('api_yuebao')->where('user_id', $user['id'])->find();
            if ($yuebao) {
                Db::name('api_yuebao')->where('user_id', $user['id'])->update([
                    'amount' => $yuebao['amount'] + $request->param('amount')
                ]);
            } else {
                Db::name('api_yuebao')->where('user_id', $user['id'])->insert([
                    'user_id' => $user['id'],
                    'amount' => $request->param('amount'),
                    'addtime' => time()
                ]);
            }
            Db::name('api_user')->where('id', $user['id'])->update([
                'price' => $user['price'] - $request->param('amount'),
            ]);
            $result = Db::name('api_fund_detail')->insert([
                'data_type' => 12,
                'user_id' => $user['id'],
                'price' => $request->param('amount'),
                'before' => $user['price'],
                'after' => $user['price'] - $request->param('amount'),
                'status' => 1,
                'remarks' => '余额宝转入金额：' . $request->param('amount'),
                'create_time' => date('Y-m-d H:i:s'),
            ]);
            Db::commit();
            return returnJson(200, '转入成功！');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '转入失败!' . $e->getMessage() . $e->getLine());
        }
    }

    // 余额宝记录
    public function yuebao_log(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $count = Db::name('api_yuebao_log')->where('user_id', $user['id'])->where('income_time', '>', strtotime(date('2023-1-16')))->count();
        $list = Db::name('api_yuebao_log')->where('user_id', $user['id'])->where('income_time', '>', strtotime(date('2023-1-16')))->page((int)$request->param('page', 1), (int)$request->param('page_size', 10))->order('income_time desc')->select();
        $data = [];
        foreach ($list as $v) {
            $v['income_time'] = date('Y-m-d H:i:s', $v['income_time']);
            $data[] = $v;
        }

        return returnJson(200, '成功', [
            'data' => $data,
            'count' => $count
        ]);;
    }

    public function transfer_out(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $__token__ = cache('token_transfer_' . $user['id']);
        if (!empty($request->param('__token__')) && $__token__ != $request->param('__token__')) {
            cache('token_transfer_' . $user['id'], null);
            return returnJson(400, '您的提交太频繁');
        } else {
            cache('token_transfer_' . $user['id'], null);
        }
        if (empty($request->param('amount')) || empty($request->param('payword'))) {
            return returnJson(400, '参数缺失');
        }
        if ($user['payword'] != md5(md5($request->param('payword')))) {
            return returnJson(400, '资金密码错误');
        }
        $yuebao = Db::name('api_yuebao')->where('user_id', $user['id'])->find();
        if (!$yuebao) {
            return returnJson(400, '非法操作');
        }
        if ($yuebao['amount'] < $request->param('amount')) {
            return returnJson(400, '余额宝余额不足');
        }
        Db::startTrans();
        try {
            Db::name('api_yuebao')->where('user_id', $user['id'])->update([
                'amount' => $yuebao['amount'] - $request->param('amount')
            ]);
            $result = Db::name('api_fund_detail')->insert([
                'data_type' => 13,
                'user_id' => $user['id'],
                'price' => $request->param('amount'),
                'before' => $user['price'],
                'after' => $user['price'] + $request->param('amount'),
                'status' => 1,
                'remarks' => '余额宝转出金额：' . $request->param('amount'),
                'create_time' => date('Y-m-d H:i:s'),
            ]);
            Db::name('api_user')->where('id', $user['id'])->update([
                'price' => $user['price'] + $request->param('amount'),
            ]);
            Db::commit();
            return returnJson(200, '转出成功！');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '转出失败!' . $e->getMessage() . $e->getLine());
        }
    }

    public function donate(Request $request)
    {
        //用户信息
        $user = $this->user;
        //公益信息
        $publicwelfare = Db::name('api_user_publicwelfare')->where('id', $request->param('id'))->find();
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        if (empty($request->param('amount')) || empty($request->param('payword'))) {
            return returnJson(400, '参数缺失');
        }
        if ($request->param('amount') < 1) {
            return returnJson(400, '最低金额不能低于1元');
        }
        // if($request->param('amount') >= 10){
        //     return returnJson(400, '最大捐赠金额不能超过10元');
        // }
        if ($user['payword'] != md5(md5($request->param('payword')))) {
            return returnJson(400, '资金密码错误');
        }
        if ($request->param('amount') > $user['price']) {
            return returnJson(400, '余额不足');
        }
        Db::startTrans();
        try {
            Db::name('api_user')->where('id', $user['id'])->update([
                'price' => $user['price'] - $request->param('amount'),
            ]);
            $result = Db::name('api_fund_detail')->insert([
                'data_type' => 14,
                'user_id' => $user['id'],
                'price' => $request->param('amount'),
                'before' => $user['price'],
                'after' => $user['price'] - $request->param('amount'),
                'status' => 1,
                'remarks' => '爱心捐赠：' . $request->param('amount'),
                'create_time' => date('Y-m-d H:i:s'),
            ]);
            Db::commit();
            return returnJson(200, '捐赠成功！');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '捐赠失败!' . $e->getMessage() . $e->getLine());
        }
    }
    //用户升级
    public function uplevel(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $ret = false;
        if ($user['offline_auths'] >= 100 && $user['level'] == 0) {
            $ret = Db::name('api_user')->where('id', $user['id'])->update([
                'level' => 4
            ]);
        } else if ($user['offline_auths'] >= 500 && $user['level'] == 1) {
            $ret = Db::name('api_user')->where('id', $user['id'])->update([
                'level' => 3
            ]);
        } else if ($user['offline_auths'] >= 1000 && $user['level'] == 2) {
            $ret = Db::name('api_user')->where('id', $user['id'])->update([
                'level' => 2
            ]);
        } else if ($user['offline_auths'] >= 5000 && $user['level'] == 3) {
            $ret = Db::name('api_user')->where('id', $user['id'])->update([
                'level' => 1
            ]);
        }
        if ($ret) {
            return returnJson(200, '升级成功！');
        } else {
            return returnJson(200, '非法操作！');
        }
    }

    //每月任务记录
    public function month_task()
    {
        header('content-type:text/html;charset=utf-8');
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $date = time();

        if (date('d') >= 10) {
            $month_start = strtotime(date("Y-m-10 00:00:00"));
            $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 months', strtotime(date('Y-m-1')))));
        } else {
            $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month', strtotime(date('Y-m-1')))));
            $month_end = strtotime(date("Y-m-9 23:59:59"));
        }

        $list = Db::name('api_task')->where('user_id', $user['id'])->where('month_start', $month_start)->where('month_end', $month_end)->find();
        $debug = Db::name('api_task')->getLastSql();
        $buy_sum = $this->get_group_buy($user['id']);
        if ($list['group_buy'] > $buy_sum) {
            $buy_sum = $list['group_buy'];
        }
        $param = [];
        if ($user['level'] == 4) {
            if ($list['offline_auths'] >= 50) {
                $param['offline_auths'] = 100;
            } else {
                $param['offline_auths'] = round($list['offline_auths'] / 50 * 100, 2);
            }
            if ($list['recharge_buys'] >= 20) {
                $param['recharge_buys'] = 100;
            } else {
                $param['recharge_buys'] = round($list['recharge_buys'] / 20 * 100, 2);
            }
            if ($list['sub1_recharge'] >= 10) {
                $param['sub1_recharge'] = 100;
            } else {
                $param['sub1_recharge'] = round($list['sub1_recharge'] / 10 * 100, 2);
            }
        } else if ($user['level'] == 3) {
            if ($list['offline_auths'] >= 100) {
                $param['offline_auths'] = 100;
            } else {
                $param['offline_auths'] = round($list['offline_auths'] / 100 * 100, 2);
            }
            if ($list['sub1_buy'] >= 50) {
                $param['sub1_buy'] = 100;
            } else {
                $param['sub1_buy'] = round($list['sub1_buy'] / 50 * 100, 2);
            }
        } else if ($user['level'] == 2) {
            if ($list['offline_auths'] >= 200) {
                $param['offline_auths'] = 100;
            } else {
                $param['offline_auths'] = round($list['offline_auths'] / 200 * 100, 2);
            }
            if ($list['sub1_buy'] >= 80) {
                $param['sub1_buy'] = 100;
            } else {
                $param['sub1_buy'] = round($list['sub1_buy'] / 80, 2);
            }
            if ($buy_sum >= 1000000) {
                $param['group_buy'] = 100;
            } else {
                $param['group_buy'] = round($buy_sum / 1000000 * 100, 4);
            }
        } else if ($user['level'] == 1) {
            if ($list['offline_auths'] >= 200) {
                $param['offline_auths'] = 100;
            } else {
                $param['offline_auths'] = round($list['offline_auths'] / 200 * 100, 2);
            }
            if ($list['sub1_buy'] >= 50) {
                $param['sub1_buy'] = 100;
            } else {
                $param['sub1_buy'] = round($list['sub1_buy'] / 50 * 100, 2);
            }
            if ($list['sub2_buy'] >= 30) {
                $param['sub2_buy'] = 100;
            } else {
                $param['sub2_buy'] = round($list['sub2_buy'] / 30 * 100, 2);
            }
            if ($list['sub1_special'] >= 10) {
                $param['sub1_special'] = 100;
            } else {
                $param['sub1_special'] = round($list['sub1_special'] / 10 * 100, 2);
            }
            if ($buy_sum >= 2000000) {
                $param['group_buy'] = 100;
            } else {
                $param['group_buy'] = round($buy_sum / 2000000 * 100, 4);
            }
        }
        return returnJson(200, '成功！', ['data' => $param, 'list' => $list, 'status1' => $list['status1'], 'status2' => $list['status2'], 'status3' => $list['status3'], 'status4' => $list['status4'], 'debug' => $debug, 'month_start' => $month_start, 'month_end' => $month_end, 'day' => date('Y-m-d H:i:s', strtotime('-1 month'))]);
    }

    public function task_pick_up(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        if (empty($request->param('status'))) {
            return returnJson(400, '参数缺失!');
        }
        $date = time();
        if (date('d') >= 10) {
            $month_start = strtotime(date("Y-m-10 00:00:00"));
            $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 months', strtotime(date('Y-m-1')))));
        } else {
            $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month', strtotime(date('Y-m-1')))));
            $month_end = strtotime(date("Y-m-9 23:59:59"));
        }
        $list = Db::name('api_task')->where('user_id', $user['id'])->where('month_start', $month_start)->where('month_end', $month_end)->find();

        //无论什么等级的代理，月任务只能领取一次，
        if ($list['status1'] > 0 || $list['status2'] > 0 || $list['status3'] > 0 || $list['status4'] > 0) {
            return returnJson(400, '已领取过了!');
        } else {
            $buy_sum = $this->get_group_buy($user['id']);
            $param = [];
            if ($user['level'] == 4) {
                if ($list['offline_auths'] >= 50 && $list['recharge_buys'] >= 20 && $list['sub1_recharge'] >= 10) {
                    Db::name('api_task')->where('id', $list['id'])->update([
                        'status4' => 1
                    ]);
                    Db::name('api_user')->where('id', $user['id'])->update([
                        'task_price' => 2000
                    ]);
                } else {
                    return returnJson(400, '没有完成任务!');
                }
            } else if ($user['level'] == 3) {
                if ($list['offline_auths'] >= 100 && $list['sub1_buy'] >= 50) {
                    Db::name('api_task')->where('id', $list['id'])->update([
                        'status3' => 1
                    ]);
                    Db::name('api_user')->where('id', $user['id'])->update([
                        'task_price' => 5000
                    ]);
                } else {
                    return returnJson(400, '没有完成任务!');
                }
            } else if ($user['level'] == 2) {
                if ($list['offline_auths'] >= 200 && $list['sub1_buy'] >= 80 && $buy_sum >= 1000000) {
                    Db::name('api_task')->where('id', $list['id'])->update([
                        'status2' => 1
                    ]);
                    Db::name('api_user')->where('id', $user['id'])->update([
                        'task_price' => 10000
                    ]);
                } else {
                    return returnJson(400, '没有完成任务!');
                }
            } else if ($user['level'] == 1) {
                if ($list['offline_auths'] >= 200 && $list['sub1_buy'] >= 50 && $list['sub2_buy'] >= 30 && $list['sub1_special'] >= 10 && $buy_sum >= 2000000) {
                    Db::name('api_task')->where('id', $list['id'])->update([
                        'status1' => 1
                    ]);
                    Db::name('api_user')->where('id', $user['id'])->update([
                        'task_price' => 50000
                    ]);
                } else {
                    return returnJson(400, '没有完成任务!');
                }
            } else {
                return returnJson(400, '没有任务!');
            }
        }
        return returnJson(200, '领取成功!');
    }

    public function vouchers()
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $list = Db::name('api_voucher')->select()->toArray();
        foreach ($list as &$v) {
            if ($v['product_id'] != 0) {
                $v['name'] = Db::name('api_product')->where('id', $v['product_id'])->value('name');
            } else {
                $v['name'] = '任意产品';
            }
            if ($v['method'] == 1) {
                $task = "邀请" . $v['num'] . "人注册并实名即可领取";
            } else {
                $task = "下级有" . $v['num'] . "人完成激活即可领取";
            }
            $v['task'] = $task;
            if ($v['method'] == 1 && $user['offline_auths'] >= $v['num']) {
                $count = Db::name('api_benefits')->where('product_id', $v['product_id'])->where('user_id', $user['id'])->where('amount', $v['amount'])->count();
                if ($count) {
                    $v['is_receive'] = 0;
                } else {
                    $v['is_receive'] = 1;
                }
                $v['finish'] = $user['offline_auths'];
                if ($user['offline_auths'] >= $v['num']) {
                    $v['percentage'] = 100;
                } else {
                    $v['percentage'] = round($user['offline_auths'] / $v['num'] * 100, 2);
                }
            } else if ($v['method'] == 2 && $user['offline_buys'] >= $v['num']) {
                $count = Db::name('api_benefits')->where('product_id', $v['product_id'])->where('user_id', $user['id'])->where('amount', $v['amount'])->count();
                if ($count) {
                    $v['is_receive'] = 0;
                } else {
                    $v['is_receive'] = 1;
                }
                $v['finish'] = $user['offline_buys'];
                if ($user['offline_buys'] >= $v['num']) {
                    $v['percentage'] = 100;
                } else {
                    $v['percentage'] = round($user['offline_buys'] / $v['num'] * 100, 2);
                }
            } else {
                $v['is_receive'] = 0;
                $v['finish'] = 0;
                $v['percentage'] = 0;
            }
        }
        return returnJson(200, '成功', [
            'data' => $list,
            'count' => count($list)
        ]);
    }

    public function get_benefits(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $user = Db::name('api_user')->where('id', $user['id'])->find();
        if (empty($request->param('id'))) {
            return returnJson(400, '参数缺失!');
        }

        $voucher = Db::name('api_voucher')->where('id', $request->param('id'))->find();
        if ($voucher['method'] == 1) {
            if ($user['offline_auths'] >= $voucher['num']) {
                $count = Db::name('api_benefits')->where('user_id', $user['id'])->where('amount', $voucher['amount'])->where('product_id', $voucher['product_id'])->count();
                if (!$count) {
                    Db::name('api_benefits')->insert([
                        'user_id' => $user['id'],
                        'amount'  => $voucher['amount'],
                        'product_id' => $voucher['product_id'],
                        'invalidtime' => time() + 7 * 86400,
                        'addtime' => time()
                    ]);
                } else {
                    return returnJson(400, '奖励已领取过!');
                }
            } else {
                return returnJson(400, '未达到领取条件!');
            }
        } else {
            if ($user['offline_buys'] >= $voucher['num']) {
                $count = Db::name('api_benefits')->where('user_id', $user['id'])->where('amount', $voucher['amount'])->count();
                if (!$count) {
                    Db::name('api_benefits')->insert([
                        'user_id' => $user['id'],
                        'amount'  => $voucher['amount'],
                        'product_id' => $voucher['product_id'],
                        'invalidtime' => time() + 7 * 86400,
                        'addtime' => time()
                    ]);
                } else {
                    return returnJson(400, '奖励已领取过!');
                }
            } else {
                return returnJson(400, '未达到领取条件!');
            }
        }
        return returnJson(200, '领取成功！');
    }

    public function benefits(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $benefits = Db::name('api_benefits')
            ->alias('ab')
            ->leftJoin('api_voucher av', 'av.id=ab.voucher_id')
            ->field('ab.*,av.name,av.image,av.endtime')
            ->where('ab.user_id', $user['id'])
            ->where('ab.status', 0)
            ->order('ab.status asc,ab.type asc,ab.id desc');
        $count = (clone $benefits)
            ->count();
        $benefits = $benefits
            ->page((int)$request->param('page', 1), (int)$request->param('page_size', 10))
            ->select()->all();
        foreach ($benefits as $k => $v) {
            // if (strtotime($v['endtime']) < time()) {
            // $benefits[$k]['status'] = 2;
            // }
            $benefits[$k]['addtime'] = date('Y-m-d', $v['addtime']);
        }
        return returnJson(200, '成功', [
            'data' => $benefits,
            'count' => $count
        ]);
    }

    public function subscribe_submit(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $user = Db::name('api_user')->where('id', $user['id'])->find();
        $product = Db::name('api_product')
            ->where('id', $request->param('product_id'))
            ->where('delete_time IS NULL')
            ->find();

        if (!$product) {
            Db::rollback();
            return returnJson(400, '未找到当前产品');
        }
        if ($request->param('product_id') == 27) {
            $count = Db::name('api_subscribe')->where('status', '>', 0)->where('user_id', $user['id'])->where('product_id', 27)->where("(" . time() . "-UNIX_TIMESTAMP(create_time))/86400 <= 30")->count();
            if ($count) {
                return returnJson(400, '当前产品30天内只能购买一次');
            }
        }

        if ($product['product_status'] != 1) {
            Db::rollback();
            return returnJson(400, '当前产品已售罄!');
        }

        if ($product['price'] != $request->param('price')) {
            Db::rollback();
            return returnJson(400, '产品金额存在变化,请重新认购');
        }

        if (empty($request->param('file_path'))) {
            Db::rollback();
            return returnJson(400, '缺少认购证书');
        }

        if (empty($request->param('order_number'))) {
            Db::rollback();
            return returnJson(400, '缺少订单号');
        }

        Db::startTrans();
        try {
            $is_curl_pay = 0;
            $curl_pay_price = 0;
            if (($user['price'] - $product['price']) < 0) {
                $is_curl_pay = 1;
                $deduction_amount_price = $user['price'];
                $curl_pay_price = $product['price'] - $deduction_amount_price;
                Db::rollback();

                return returnJson(212, '账户余额不足');
            } else {
                $price = $product['price'];
                $result = Db::name('api_user')->where('id', $user['id'])->dec('price', (float)$price)->update();
            }


            if ($product['product_type'] == 3 && $product['method'] == 1) {
                $end_time = time() + (86400 * $product['cycle']);
            } else if ($product['product_type'] == 3 && $product['method'] == 3) {
                $end_time = time() + ($product['cycle'] * 60);
            } else {
                Db::rollback();
                return returnJson(400, '非法操作');
            }
            $estimated_total_revenue = round($product['price'] + $product['proceeds'], 2);
            $insert_array = [
                'user_id' => $user['id'],
                'product_id' => $product['id'],
                'product_type' => $product['product_type'],
                'method'    => $product['method'],
                'name' => $product['name'],
                'image' => $product['image'],
                'price' => $product['price'],
                'cycle' => $product['cycle'],
                'end_time' => date('Y-m-d H:i:s', $end_time),
                'proceeds' => $product['proceeds'],
                'status' => 1,
                'estimated_total_revenue' => $estimated_total_revenue, // 预计收入
                'amount_of_income_received' => 0, // 已收益金额
                'username' => $user['username'],
                'id_card' => $user['id_card'],
                'order_number' => $request->param('order_number'),
                'subscription_certificate' => $request->param('file_path'),
                'create_time' => date('Y-m-d H:i:s'),
            ];
            if ($is_curl_pay) {
                $insert_array['delete_time'] = date('Y-m-d H:i:s');
                $insert_array['status'] = 0;
                $insert_array['deduction_amount_price'] = $deduction_amount_price;
            } else {
                $count = Db::name('api_subscribe')->where('status', '>', 0)->where('user_id', $user['id'])->count();
                $config = config('web');

                $first_parent = Db::name('api_user')->where('id', $user['id'])->value('parent_user_id');
                // if(!$count){
                //     Db::name('api_fund_detail')->insert([
                //         'data_type'     => 11,
                //         'user_id' => $first_parent,
                //         'price' => 1000,
                //         'status' => 3,
                //         'remarks' => '产品购买,订单号:' . $request->param('order_number'),
                //         'create_time' => date('Y-m-d H:i:s'),
                //     ]);
                //     Db::name('api_user')->where('id', $first_parent)->inc('promotion_reward_total', 1000)->update();
                // }
                if ($product['cycle'] > 7) {
                    Db::name('api_fund_detail')->insert([
                        'data_type'     => 9,
                        'user_id' => $first_parent,
                        'price' => (float)($product['price'] * $config['generation_reward'] / 100),
                        'status' => 1,
                        'remarks' => '产品购买,订单号:' . $request->param('order_number'),
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);

                    Db::name('api_user')->where('id', $first_parent)->inc('price', (float)($product['price'] * $config['generation_reward'] / 100))->update();
                    $second_parent = Db::name('api_user')->where('id', $first_parent)->value('parent_user_id');
                    Db::name('api_fund_detail')->insert([
                        'data_type'     => 10,
                        'user_id' => $first_parent,
                        'price' => (float)($product['price'] * $config['second-generation_rewards'] / 100),
                        'status' => 1,
                        'remarks' => '产品购买,订单号:' . $request->param('order_number'),
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
                    Db::name('api_user')->where('id', $second_parent)->inc('price', (float)($product['price'] * $config['second-generation_rewards'] / 100))->update();
                }
            }

            $id = Db::name('api_subscribe')
                ->insertGetId($insert_array);
            $result = [
                'type' => [
                    1 => [
                        'name' => '支付宝支付通道',
                        'channel' => '1109',
                        'min_price' => 10,
                        'max_price' => 10000,
                    ],
                    2 => [
                        'name' => '微信H5通道',
                        'channel' => '1003',
                        'min_price' => 1,
                        'max_price' => 5000,
                    ]
                ],
                'price' => 0,
                'min_price' => 10,
                'max_price' => 5000,
                'id' => $id
            ];

            if ($is_curl_pay) {
                Db::commit();
                $result['price'] = $curl_pay_price;
                return returnJson(210, '成功', $result);
            } else {
                $result = Db::name('api_fund_detail')
                    ->insert([
                        'data_type' => 3,
                        'user_id' => $user['id'],
                        'price' => (float)$product['price'],
                        'status' => 1,
                        'remarks' => '产品购买,订单号:' . $request->param('order_number'),
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
            }
            if (!$id) {
                Db::rollback();
                return returnJson(400, '处理异常');
            }
            Db::commit();
            return returnJson(200, '恭喜你,认购成功!可进入认购明细查看认购记录');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '认购失败!' . $e->getMessage() . $e->getLine());
        }
    }

    public function subscribe_list(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $subscription = Db::name('api_subscribe')
            ->field('*')
            ->where('user_id', $user['id'])
            // ->where('product_type', $request->param('type'))
            ->whereIn('product_type', [1, 2, 3])
            ->where('status', '<>', 3)
            ->order('create_time desc');
        $count = (clone $subscription)
            ->count();
        $subscription = $subscription
            ->page((int)$request->param('page', 1), (int)$request->param('page_size', 10))
            ->select()->all();

        foreach ($subscription as $k => $v) {
            // $subscription[$k]['proceeds'] = Db::name('api_product')->where('id', $v['product_id'])->value('proceeds');
            $product = Db::name('api_product')->where('id', $v['product_id'])->field('income,cycle_num')->find();
            $subscription[$k]['income'] = $product ? $product['income'] : 0;
            $subscription[$k]['cycle_num'] = $product ? $product['cycle_num'] : 0;
            $subscription[$k]['gary'] = (bool) Db::name('api_subscribe')->whereDay('update_time')->where('id', $v['id'])->where('user_id', $user['id'])->count();
        }
        return returnJson(200, '成功', [
            'data' => $subscription,
            'count' => $count
        ]);
    }

    public function banks()
    {
        $user = $this->user;
        if (!$user) {
            Db::rollback();
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $list = Db::name('api_bank')->select();
        return returnJson(200, '成功', [
            'data' => $list,
            'count' => count($list)
        ]);
    }

    public function subscription_list(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $subscription = Db::name('api_subscription')
            ->field('*')
            ->where('delete_time IS NULL')
            ->where('user_id', $user['id'])
            ->where('delete_time IS NULL')
            ->when($request->param('status'), function ($query, $data) {
                return $query
                    ->where('status', $data);
            })->order('create_time desc');

        $count = (clone $subscription)
            ->count();

        $subscription = $subscription
            ->page((int)$request->param('page', 1), (int)$request->param('page_size', 10))
            ->withAttr('status', function ($value, $data) {
                if ($value == 2) return $value;
                if ($value == 1 && strtotime($data['end_time']) < time() && $data['product_type'] == 1) {
                    return 2;
                } else {
                    return $value;
                }
            })
            ->select();

        return returnJson(200, '成功', [
            'data' => $subscription,
            'count' => $count
        ]);
    }
    //个人中心
    public function personal_center(Request $request)
    {
        header('Content-Type:text/html;charset:utf-8');
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        //var_dump($user);exit;
        $config = config('web');
        $check_time = Db::name('api_check_time')
            ->where('user_id', $user['id'])
            ->where('stype', 1)
            ->order('create_time desc')
            ->find();
        $isset_check_time = 2;
        if ($check_time && strtotime(date('Y-m-d')) <= $check_time['create_time'] && strtotime(
            date('Y-m-d 23:59:59')
        ) >= $check_time['create_time']) {
            $isset_check_time = 1;
        }
        $sub = Db::name('api_subscription')->where('user_id', $user['id'])->where('product_type', 1)->where('delete_time is null')->where('status', 1)->find();
        $daitixian = 0.00;
        if ($sub) {
            $daitixian = ($sub['price'] / 2) + ($sub['price'] * $sub['proceeds'] / 100 / 2);
        }
        $issetpwd = 1;
        if ($user['payword'] == '') {
            $issetpwd = 0;
        }
        $schedule = 0;
        if ($user['level'] == 0) {
            if ($user['offline_auths'] >= 100) {
                $schedule = 100;
            } else {
                $schedule = $user['offline_auths'];
            }
        }
        if ($user['level'] == 4) {
            if ($user['offline_auths'] >= 500) {
                $schedule = 100;
            } else {
                $schedule = number_format(($user['offline_auths'] - 100) / 400 * 100, 2, '.', '');
            }
        }
        if ($user['level'] == 3) {
            if ($user['offline_auths'] >= 1000) {
                $schedule = 100;
            } else {
                $schedule = number_format(($user['offline_auths'] - 500) / 500 * 100, 2, '.', '');
            }
        }
        if ($user['level'] == 2) {
            if ($user['offline_auths'] >= 5000) {
                $schedule = 100;
            } else {
                $schedule = number_format(($user['offline_auths'] - 1000) / 5000 * 100, 2, '.', '');
            }
        }

        $sign_in_status = 0;
        if ($check_time) {
            if (strtotime(date('Y-m-d')) <= $check_time['create_time'] && strtotime(
                date('Y-m-d 23:59:59')
            ) >= $check_time['create_time']) {
                $sign_in_status = 1;
            }
        }
        $start = strtotime(date('Y-m-d 00:00:00'));
        $end = strtotime(date('Y-m-d 23:59:59'));
        $sign_amount = Db::name('api_check_time')->where('user_id', $user['id'])->where('stype', 2)->where('create_time', '>=', $start)->where('create_time', '<=', $end)->order('id', 'desc')->value('integral');
        $sign_num = Db::name('api_fund_detail')->where('user_id', $user['id'])->where('data_type', 11)->count();
        $pension_profit = Db::name('api_fund_detail')->whereDay('create_time')->where('data_type', 27)->where('user_id', $user['id'])->count();
        $pension_apply_info = Db::name('api_user_pension')->where('user_id', $user['id'])->field('username,id_card,status,level_id as lid,pay_status')->order('create_time', 'desc')->find();
        $coupon_total = (new ApiSubscribe())->getCouponTotal($user);
        $__token__ = md5(time() . rand(100000, 999999));
        $benefits = Db::name('api_benefits')
            ->alias('ab')
            ->leftJoin('api_voucher av', 'av.id=ab.voucher_id')
            ->field('ab.id,av.amount,av.name,av.image')
            ->where('ab.user_id', $user['id'])
            ->where('ab.status', 0)
            ->where('ab.type', 0)
            // ->where('ab.invalidtime', '>', time())
            ->order('ab.amount desc,ab.id desc');
        $total = $benefits->count();
        $benefits = $benefits->limit(10)->select();
        if ($benefits) {
            $benefits = $benefits->toArray();
            foreach ($benefits as &$item) {
                $item['checked'] = false;
            }
        }
        $twoActiveCount = Db::name('api_subscribe')->where('user_id', $user['id'])->whereLike('name', '%二次账户验证%')->count();
        $twoActiveCount = $twoActiveCount && $user['huimin_apply'] == 2 ? 1 : 0;
        $levelName = $user['level'] == 0 ? '普通会员' : Db::name('api_level')->where('id', $user['level'])->value('name');
        $bank_apply_info = get_fun_apply_bank_profit($user['cash_price']);
        // $apply_info = Db::name('api_user_apply_bank')->where('user_id', $user['id'])->where('is_delete',0)->find();
        $apply_info = Db::name('api_user_apply_bank')->where('user_id', $user['id'])->order('id desc')->find();
        // halt($apply_info);
        cache('token_transfer_' . $user['id'], $__token__);
        return returnJson(200, '成功', [
            'status'           => $user['status'],
            'id'               => $user['id'],
            'mobile'           => $user['mobile'],
            'benefits'         => $benefits,
            'benefits_total'    => $total,
            'active_count'    => $twoActiveCount,
            'price'            => $user['price'],  // 可提现金额
            'pension_price'    => $user['pension_price'],
            'pension_profit'    => $pension_profit,
            'pension_info'    => $pension_apply_info,
            'bank_info'    => $bank_apply_info,
            'bank_apply'    => $apply_info,
            'huimin_price'    => $user['huimin_price'],
            'apply_status'    => $user['huimin_apply'],
            'withdraw_price'    => $user['withdraw_price'],
            'withdraw_disabled'    => true,
            'cash_price'        => $user['cash_price'],
            'issetpwd'         => $issetpwd,
            'yuebao'           => Db::name('api_yuebao')->where('user_id', $user['id'])->value('amount'),
            'invitees'         => $user['offline_auths'],
            'level'            => $user['level'],
            'levelName'        => $levelName,
            'schedule'         => $schedule,
            'sign_status'      => $sign_in_status,
            'sign_amount'      => $sign_amount,
            'sign_num'         => $sign_num,
            'coupon_total'     => $coupon_total,
            'sign_in_balace'   => $user['sign_in_balace'],
            'oxygen'           => $user['oxygen'],
            'task_price'       => $user['task_price'],
            'transfer_status'  => $user['transfer_status'], //转账权限   
            'limit_with'       => $user['limit_with'], //提款权限 1 正常  2禁止
            'limit_bene'       => $user['limit_bene'], //收益权限 1 正常  2禁止
            'sign_schedule'    => empty($sign_amount) ? 0 : number_format($sign_amount, 2, '.', ''),
            '__token__'        => $__token__
        ]);
    }

    public function get_planting(Request $request)
    {
        $start = strtotime(date('Y-m-d 00:00:00'));
        $end = strtotime(date('Y-m-d 23:59:59'));
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        if (empty($request->param('sign_amount'))) {
            return returnJson(400, '参数缺失');
        }
        // print_r($request->param());
        // echo  (float) $request->param();
        // exit();
        $sign_list = Db::name('api_check_time')
            ->where('user_id', $user['id'])
            ->where('stype', 2)
            ->whereTime('create_time', 'between', [$start, $end])
            ->where('integral', $request->param('sign_amount'))
            ->find();
        if ($sign_list && $sign_list['status'] == 1) {
            return returnJson(400, '已领取过');
        }

        $auths = Db::name('api_monthlog')->where('user_id', $user['id'])->where('addtime', '>=', $start)->where('addtime', '<=', $end)->count();
        if ($auths >= 2 && $auths < 7) {
            $sign_amount = 20;
        }
        if ($auths >= 7 && $auths < 17) {
            $sign_amount = 50;
        }
        if ($auths >= 17) {
            $sign_amount = 100;
        }
        if ($sign_amount >= (float)$request->param('sign_amount')) {
            Db::name('api_user')->where('id', $user['id'])->inc('sign_in_balace', (float)$request->param('sign_amount'))->update([
                'last_sign_time' => strtotime(date('Y-m-d 00:00:00'))
            ]);
            Db::name('api_check_time')->where('user_id', $user['id'])->where('stype', 2)->where('integral', $request->param('sign_amount'))->update([
                'status' => 1
            ]);
            return returnJson(200, '成功');
        } else {
            return returnJson(400, '非法操作');
        }
    }
    //签到
    public function user_check_time(Request $request)
    {
        header('Content-Type:text/html;charset:utf-8');
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $check_time = Db::name('api_check_time')
            ->where('user_id', $user['id'])
            ->where('stype', 1)
            ->order('create_time desc')
            ->find();

        $continuous_check_in_time = 0;
        if ($check_time) {
            if (strtotime(date('Y-m-d')) <= $check_time['create_time'] && strtotime(
                date('Y-m-d 23:59:59')
            ) >= $check_time['create_time']) {

                return returnJson(400, '今天已经签到啦!');
            }

            $start_time = strtotime(date('Y-m-d')) - 86400;
            $end_time = strtotime(date('Y-m-d 23:59:59')) - 86400;
            if ($check_time['create_time'] >= $start_time && $check_time['create_time'] <= $end_time) {
                $continuous_check_in_time = $check_time['continuous_check_in_time'] + 1;
            }
        }
        $config = config('web');
        $integral = $config['sign_in_reward'];
        $result = Db::name('api_check_time')
            ->insert([
                'user_id' => $user['id'],
                'create_time' => time(),
                'continuous_check_in_time' => $continuous_check_in_time,
                'integral' => $integral,
            ]);
        Db::name('api_user')->where('id', $user['id'])->update([
            'sign_in_balace' => Db::raw('sign_in_balace+' . $integral)
        ]);


        if (!$result) {
            Db::rollback();
            return returnJson(400, '签到失败,请重试');
        }
        return returnJson(200, '签到成功', ['integral' => $result['integral']]);
    }

    public function get_oxygen()
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        if ($user['sign_in_balace'] >= 890) {
            $ret = Db::name('api_user')->where('id', $user['id'])->inc('oxygen', 49)->dec('sign_in_balace', 890)->update();
            if ($ret) {
                return returnJson(200, '签到成功');
            }
        }
        return returnJson(400, '获取失败');
    }

    public function oxygen_to_price(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        if (empty($request->param('oxygen'))) {
            return returnJson(400, '参数缺失');
        }
        $oxygen = $request->param('oxygen');
        $ret = false;
        Db::startTrans();
        try {
            if ($oxygen == 50 && $user['oxygen'] >= 50) {
                $ret = Db::name('api_user')->where('id', $user['id'])->dec('oxygen', 50)->update();
                $result = Db::name('api_fund_detail')->insert([
                    'data_type' => 15,
                    'user_id' => $user['id'],
                    'price' => 200,
                    'oxygen' => $oxygen,
                    'status' => 3,
                    'remarks' => '氧气转换金额：200',
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            } else if ($oxygen == 100 && $user['oxygen'] >= 100) {
                $ret = Db::name('api_user')->where('id', $user['id'])->dec('oxygen', 100)->update();
                $result = Db::name('api_fund_detail')->insert([
                    'data_type' => 15,
                    'user_id' => $user['id'],
                    'price' => 400,
                    'oxygen' => $oxygen,
                    'status' => 3,
                    'remarks' => '氧气转换金额：400',
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            } else if ($oxygen == 200 && $user['oxygen'] >= 200) {
                $ret = Db::name('api_user')->where('id', $user['id'])->dec('oxygen', 200)->update();
                $result = Db::name('api_fund_detail')->insert([
                    'data_type' => 15,
                    'user_id' => $user['id'],
                    'price' => 1000,
                    'oxygen' => $oxygen,
                    'status' => 3,
                    'remarks' => '氧气转换金额：1000',
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            } else if ($oxygen == 500 && $user['oxygen'] >= 500) {
                $ret = Db::name('api_user')->where('id', $user['id'])->dec('oxygen', 500)->update();
                $result = Db::name('api_fund_detail')->insert([
                    'data_type' => 15,
                    'user_id' => $user['id'],
                    'price' => 3000,
                    'oxygen' => $oxygen,
                    'status' => 3,
                    'remarks' => '氧气转换金额：3000',
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            } else if ($oxygen == 1000 && $user['oxygen'] >= 1000) {
                $ret = Db::name('api_user')->where('id', $user['id'])->dec('oxygen', 1000)->update();
                $result = Db::name('api_fund_detail')->insert([
                    'data_type' => 15,
                    'user_id' => $user['id'],
                    'price' => 6000,
                    'oxygen' => $oxygen,
                    'status' => 3,
                    'remarks' => '氧气转换金额：6000',
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            } else if ($oxygen == 5000 && $user['oxygen'] >= 5000) {
                $ret = Db::name('api_user')->where('id', $user['id'])->dec('oxygen', 5000)->update();
                $result = Db::name('api_fund_detail')->insert([
                    'data_type' => 15,
                    'user_id' => $user['id'],
                    'price' => 48000,
                    'oxygen' => $oxygen,
                    'status' => 3,
                    'remarks' => '氧气转换金额：48000',
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            } else {
                return returnJson(400, '兑换失败');
            }
            Db::commit();
            return returnJson(200, '兑换请求成功，请等待后台处理');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '处理异常');
        }
        return returnJson(400, '兑换失败');
    }

    public function my_team(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $config = config('web');
        $all_recharge = Db::name('api_fund_detail')->where('user_id', $user['id'])->where('data_type', 2)->where('status', 1)->sum('price');
        if ($all_recharge < 10000) {
            $parent_level = '普通会员';
        } else if ($all_recharge >= 10000 && $all_recharge < 30000) {
            $parent_level = '白银会员';
        } else if ($all_recharge >= 30000 && $all_recharge < 50000) {
            $parent_level = '白钻会员';
        } else if ($all_recharge >= 50000) {
            $parent_level = '荣誉董事';
        }

        $total_one = Db::name('api_user')
            ->where('parent_user_id', $user['id'])
            ->column('id');

        $total_tow = [];
        if (!empty($total_one)) {
            $total_tow = Db::name('api_user')
                ->where('parent_user_id', 'in', $total_one)
                ->column('id');
        }

        $user_id = array_merge($total_one, $total_tow);
        $subscription = [];
        if (!empty($user_id)) {
            // $user_id[] = $user['id'];
            $subscription = Db::name('api_subscription')
                ->field('cash_back_amount_per_day,estimated_total_revenue,status,amount_of_income_received,cycle')
                ->where('user_id', 'in', $user_id)
                ->where('delete_time IS NULL')
                ->select();
        }

        $earnings_today = 0;
        $total_price = 0;

        foreach ($subscription as $item) {
            if ($item['status'] == 2) {
                $total_price += $item['cash_back_amount_per_day'] * $item['cycle'];
                continue;
            }

            $total_price += $item['amount_of_income_received'];
            $earnings_today += $item['cash_back_amount_per_day'];
        }

        return returnJson(200, '成功', [
            'team_size' => count($total_one) + count($total_tow),
            'earnings_today' => $earnings_today,
            'total_price' => $total_price,
            'direct_subordinate' => count($total_one),
            'level' => $parent_level,
            // 'introduction_to_team_benefits' => config('web')['introduction_to_team_benefits'],
        ]);
    }

    public function team_info()
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $user_id = $user['id'];
        $key = $user_id . "_team_allIds";
        $allIds = cache($key) ?? ApiUser::get_level_allIds($user_id);
        $data = [
            'team_total_price' => sprintf("%.2f", Db::name('api_subscribe')->whereIn('user_id', $allIds)->sum('price') ?? 0),
            'today_total_price' => sprintf("%.2f", Db::name('api_subscribe')->whereDay('create_time')->whereIn('user_id', $allIds)->sum('price') ?? 0),
            'team_total_create' => count($allIds),
            'today_total_create' => Db::name('api_user')->whereDay('create_time')->whereIn('parent_user_id', $allIds)->count(),
            'team_total_status' => Db::name('api_user')->where('status', 1)->whereIn('id', $allIds)->count(),
            'today_total_status' => Db::name('api_user')->whereDay('create_time')->where('status', 1)->whereIn('id', $allIds)->count(),
            'team_total_active' => Db::name('api_subscribe')->whereIn('product_id', [42, 51])->whereIn('user_id', $allIds)->count(),
            'today_total_active' => Db::name('api_subscribe')->whereDay('create_time')->whereIn('product_id', [42, 51])->whereIn('user_id', $allIds)->count(),
        ];
        return returnJson(200, '成功', $data);
    }

    public function team_new(Request $request)
    {
        $user = $this->user;
        $level = $request->param('level', 0);
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $user_id = $user['id'];
        $key = $user_id . "_team_level_" . $level;
        $teamIds = cache($key) ?? ApiUser::get_level_ids($user_id, $level);
        if (!cache($key)) {
            cache($key, $teamIds, 600);
        }
        $data = [
            'data' => [],
            'count' => 0,
            'level_total' => 0,
            'one_reward' => config('web')['generation_reward'],
            'tow_reward' => config('web')['second-generation_rewards']
        ];
        if ($teamIds) {
            $list = Db::name('api_user')->with('user')->whereIn('id', $teamIds)->field('id,level,username,mobile,create_time');
            $count = (clone $list)->count();
            $list = $list->page((int)$request->param('page', 1), (int)$request->param('page_size', 10))
                ->withAttr('level', function ($value, $data) {
                    return $value ? Db::name('api_level')->where('id', $value)->value('name') : '普通会员';
                })
                ->filter(function ($item) {
                    $item['create_time'] = date('Y-m-d', strtotime($item['create_time']));
                    $total_amount = Db::name('api_subscribe')->where('user_id', $item['id'])->sum('price');
                    $item['total_amount'] = sprintf("%.2f", $total_amount ?? 0);
                    return $item;
                })
                ->select();
            $data  = [
                'data' => $list,
                'count' => $count,
                'level_count' => count($teamIds),
                'one_reward' => config('web')['generation_reward'],
                'tow_reward' => config('web')['second-generation_rewards'],
            ];
        }
        return returnJson(200, '成功', $data);
    }


    public function team_distribution(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $list = $this->get_team($user['id']);
        return returnJson(200, '成功', ['list' => $list, 'count' => count($list)]);
    }

    public function get_team($user_id, $team = [], $level = 0)
    {
        $list = Db::name('api_user')->field('id,username,mobile,create_time')->where('parent_user_id', $user_id)->select();
        if (count($list) > 0) {
            $level++;
            foreach ($list as $v) {
                $v['level'] = $level;
                $v['product_name'] = Db::name('api_subscribe')->where('user_id', $v['id'])->order('price', 'desc')->value('name');
                $team = $this->get_team($v['id'], $team, $level);
                $team[] = $v;
            }
        }
        return $team;
    }

    public function get_group_buy($user_id, $buy_sum = 0, $level = 0)
    {
        $list = Db::name('api_user')->field('id,mobile,create_time')->where('parent_user_id', $user_id)->select();
        $date = time();
        if (date('d') >= 10) {
            $month_start = strtotime(date("Y-m-10 00:00:00"));
            $month_end = strtotime(date("Y-m-9 23:59:59", strtotime('+1 months', strtotime(date('Y-m-1')))));
        } else {
            $month_start = strtotime(date("Y-m-10 00:00:00", strtotime('-1 month', strtotime(date('Y-m-1')))));
            $month_end = strtotime(date("Y-m-9 23:59:59"));
        }
        if (count($list) > 0) {
            $level++;
            foreach ($list as $v) {
                $v['level'] = $level;
                $buy_sum += Db::name('api_subscribe')->where('create_time', '>=', $month_start)->where('create_time', '<', $month_end)->where('user_id', $v['id'])->sum('price');
                $buy_sum = $this->get_group_buy($v['id'], $buy_sum, $level);
            }
        }
        return $buy_sum;
    }

    public function welfare()
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $list = [];
        if ($user['offline_auths'] >= 5) {
            $w50 = 100;
        } else {
            $w50 = $user['offline_auths'] / 5 * 100;
        }
        $list = Db::name('api_voucher')->select()->toArray();
        foreach ($list as &$v) {
            if ($v['method'] == 1 && $user['offline_auths'] >= $v['num']) {
                $v['is_receive'] = 1;
            } else if ($v['method'] == 2 && $user['offline_buys'] >= $v['num']) {
                $v['is_receive'] = 1;
            } else {
                $v['is_receive'] = 0;
            }
        }

        return returnJson(200, '成功', ['data' => $list]);
    }

    public function lottery_page(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $config = config('web');
        if ($user['grow_up'] >= $config['star_head_grow_up']) {
            $level = '星耀团队长';
            $integral = $config['star_head_lottery_integral'];
        } else if ($user['grow_up'] >= $config['diamonds_grow_up']) {
            $level = '钻石团队长';
            $integral = $config['diamonds_head_lottery_integral'];
        } else if ($user['grow_up'] >= $config['gold_head_grow_up']) {
            $level = '黄金团队长';
            $integral = $config['gold_head_lottery_integral'];
        } else if ($user['grow_up'] >= $config['silver_head_grow_up']) {
            $level = '白银团队长';
            $integral = $config['silver_head_lottery_integral'];
        } else if ($user['grow_up'] >= $config['member_head_grow_up']) {
            $level = '会员团队长';
            $integral = $config['member_head_lottery_integral'];
        } else {
            $level = '普通会员';
            $integral = $config['general_member_lottery_integral'];
        }

        $award = [];
        for ($i = 1; $i < 6; $i++) {
            $award[] = [
                'award' => $config['award_' . $i],
                'award_type' => (int)$config['award_' . $i . '_type'],
            ];
        }

        $award[] = [
            'award' => '谢谢',
            'award_type' => 2,
        ];

        return returnJson(200, '成功', [
            'level' => $level,
            'integral' => $user['integral'],
            'award' => array_values($award),
            'consume_integral' => $integral,
        ]);
    }

    public function lottery(Request $request)
    {
        Db::startTrans();
        try {
            $user = $this->user;
            if (!$user) {
                Db::rollback();
                return returnJson(301, '未找到当前用户请重新登录');
            }

            $config = config('web');
            if ($user['grow_up'] >= $config['star_head_grow_up']) {
                $integral = $config['star_head_lottery_integral'];
            } else if ($user['grow_up'] >= $config['diamonds_grow_up']) {
                $integral = $config['diamonds_head_lottery_integral'];
            } else if ($user['grow_up'] >= $config['gold_head_grow_up']) {
                $integral = $config['gold_head_lottery_integral'];
            } else if ($user['grow_up'] >= $config['silver_head_grow_up']) {
                $integral = $config['silver_head_lottery_integral'];
            } else if ($user['grow_up'] >= $config['member_head_grow_up']) {
                $integral = $config['member_head_lottery_integral'];
            } else {
                $integral = $config['general_member_lottery_integral'];
            }

            if (($user['integral'] - $integral) <= 0) {
                Db::rollback();
                return returnJson(400, '抽奖失败,积分不够啦!');
            }

            $result = Db::name('api_user')
                ->where('id', $user['id'])
                ->dec('integral', (float)$integral)
                ->update();
            if (!$result) {
                Db::rollback();
                return returnJson(400, '积分扣除失败');
            }

            for ($i = 1; $i < 6; $i++) {
                $prize_arr[] = [
                    'id' => $i,
                    'prize' => $config['award_' . $i],
                    'award_type' => (int)$config['award_' . $i . '_type'],
                    'v' => (int)$config['award_' . $i . '_probability'],
                ];
            }

            $prize_arr[] = [
                'id' => 6,
                'prize' => '谢谢',
                'award_type' => 2,
                'v' => (int)$config['empty_probability'],
            ];

            foreach ($prize_arr as $key => $val) {
                $arr[$val['id']] = $val['v'];
            }
            $rid = get_rand($arr);

            $res = $prize_arr[$rid - 1]; //获取中奖项

            $status = 1;
            if ($res['id'] == 6) {
                $status = 2;
            }

            if ($status == 1) {
                $result = Db::name('api_user')
                    ->where('id', $user['id'])
                    ->inc('price', (float)string_number($res['prize']))
                    ->update();
                if (!$result) {
                    Db::rollback();
                    return returnJson(400, '余额充值失败');
                }

                $result = Db::name('api_fund_detail')
                    ->insert([
                        'data_type' => 5,
                        'user_id' => $user['id'],
                        'price' => string_number($res['prize']),
                        'status' => 1,
                        'remarks' => '抽奖中奖' . $res['prize'],
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
                if (!$result) {
                    Db::rollback();
                    return returnJson(400, '资金日志记录失败');
                }
            }

            $result = Db::name('api_luck')
                ->insert([
                    'user_id' => $user['id'],
                    'award' => $res['prize'],
                    'integral' => $integral,
                    'award_type' => $res['award_type'],
                    'create_time' => date('Y-m-d H:i:s'),
                    'status' => $status,
                ]);
            if (!$result) {
                Db::rollback();
                return returnJson(400, '抽奖记录失败');
            }

            Db::commit();
            return returnJson(200, '成功', [
                'status' => $status,
                'award' => $res['prize'],
                'index' => $rid - 1,
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '处理异常');
        }
    }

    public function lottery_log_list(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $lottery = Db::name('api_luck')
            ->field('*')
            ->where('user_id', $user['id'])
            ->where('delete_time IS NULL')
            ->order('create_time desc');

        $count = (clone $lottery)
            ->count();

        $lottery = $lottery
            ->page((int)$request->param('page', 1), (int)$request->param('page_size', 10))
            ->select();

        return returnJson(200, '成功', [
            'data' => $lottery,
            'count' => $count
        ]);
    }

    public function recharge_page(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $account = Db::name('api_account')
            ->where('user_id', $user['id'])
            ->where('delete_time IS NULL')
            ->select();
        // $recharge = Db::name('api_recharge')
        //     ->where('delete_time IS NULL')
        //     ->select();

        $recharge = Db::name('api_paylist')
            ->where('delete_time IS NULL')
            ->where('status', 1)
            ->field('id,name')
            ->order('sort desc')
            ->select();

        return returnJson(200, '成功', [
            'account' => $account,
            'recharge' => $recharge
        ]);
    }

    public function user_exit(Request $request)
    {
        $user = $this->user;
        cache('C_token_' . $user['id'], NULL);    //删除登录缓存
        return returnJson(200, '退出成功');
    }

    public function edit_password(Request $request)
    {
        if (empty($request->param('old_password'))) {
            return returnJson(400, '请输入旧密码');
        }
        if (empty($request->param('password'))) {
            return returnJson(400, '请输入新密码');
        }
        if (empty($request->param('confirm_password'))) {
            return returnJson(400, '请输入确认密码');
        }
        if ($request->param('password') != $request->param('confirm_password')) {
            return returnJson(400, '两次密码输入不一致');
        }
        if (!@preg_match("/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u", $request->param('password'))) {
            return returnJson(400, '非法操作');
        }
        $user = $this->user;

        $result = Db::name('api_user')
            ->where('id', $user['id'])
            ->where('password', md5(md5($request->param('old_password'))))
            ->where('delete_time IS NULL')
            ->update(
                [
                    'password' => md5(md5($request->param('password'))),
                    'update_time' => date('Y-m-d H:i:s'),
                ]
            );
        if (!$result) {
            return returnJson(400, '修改失败');
        }
        return returnJson(200, '成功');
    }

    public function edit_pay_password(Request $request)
    {
        $user = $this->user;

        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        if ($user['payword'] != "") {
            if (empty($request->param('old_pay_password'))) {
                return returnJson(400, '请输入旧密码');
            }
        }
        if (empty($request->param('pay_password'))) {
            return returnJson(400, '请输入新密码');
        }
        if (empty($request->param('confirm_password'))) {
            return returnJson(400, '请输入确认密码');
        }
        if ($request->param('pay_password') != $request->param('confirm_password')) {
            return returnJson(400, '两次密码输入不一致');
        }
        if (!@preg_match("/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u", $request->param('pay_password'))) {
            return returnJson(400, '非法操作');
        }
        $old_pay_password = empty($request->param('old_pay_password')) ? '' : $request->param('old_pay_password');
        $user = $this->user;

        $result = Db::name('api_user')
            ->where('id', $user['id'])
            ->when($old_pay_password, function ($query, $data) {
                return $query
                    ->where('payword', md5(md5($data)));
            })
            ->where('delete_time IS NULL')
            ->update(
                [
                    'payword' => md5(md5($request->param('pay_password'))),
                    'update_time' => date('Y-m-d H:i:s'),
                ]
            );
        if (!$result) {
            return returnJson(400, '修改失败');
        }
        return returnJson(200, '成功');
    }


    public function recharge(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $pay_id = $request->param('payId');
        $price = $request->param('price');
        $info = Db::name('api_paylist')->where('id', $pay_id)->find();
        if (!$info['status']) {
            return returnJson(201, '通道关闭，请尝试其他通道充值！');
        }
        if ($user['status'] != 1) {
            return returnJson(400, '抱歉，未实名不能进行充值');
        }
        if (!$info['mchid'] || !$info['appkey']) {
            return returnJson(201, '商户未配置！');
        }
        if ($price < $info['min']) {
            return returnJson(201, '最低充值' . $info['min'] . "元");
        }
        if ($price > $info['max']) {
            return returnJson(201, '最多充值' . $info['max'] . "元");
        }
        $order_no = 'CZ' . date('Ymdhis') . rand(100000, 999999);
        $result_data = [];
        switch ($info['payname']) {
            case 'huiyingpay':
                $data = [
                    'mchno' => $info['mchid'],
                    'obid' => $order_no,
                    'name' => 'aliapy' . rand(1000, 9999),
                    'chno' => $info['code'],
                    'amount' => floatval($price),
                    'notice_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/api.php/notify/hynotify',
                    'ouid' => $user['id'],
                    'calltype' => '2',
                ];
                $data['sign'] = get_sign_key($data, $info['appkey']);
                $payurl = "http://w55879.lobopay.xyz/api/order";
                $res = curlPost($payurl, $data);
                if ($res) {
                    $jsonData = json_decode($res, true);
                    if ($jsonData['msg'] == 'success') {
                        if ($jsonData['payurl']) {
                            $result_data['url']  = $jsonData['payurl'];
                        } else {
                            return returnJson(201, '获取支付失败，请尝试使用其他方式充值！');
                        }
                    }
                }
                break;
            case 'bafangpay':
                $data = [
                    'mchId' => $info['mchid'],
                    'appId' => $info['appid'],
                    'productId' => $info['code'],
                    'currency' => 'cny',
                    'mchOrderNo' => $order_no,
                    'amount' => floatval($price * 100),
                    'clientIp' => '183.186.' . rand(100, 255) . '.255',
                    'returnUrl' => 'https://h5.yushzg.com',
                    'notifyUrl' => 'https://' . $_SERVER['HTTP_HOST'] . '/api.php/notify/notify',
                    'subject' => '商品充值',
                    'body' => 'body',
                    'extra' => json_encode([]),
                ];
                $data['sign'] = get_sign_key($data, $info['appkey']);
                $payurl = "http://47.242.251.196:12101/api/pay/create_order";
                $res = curlPost($payurl, $data);
                if ($res) {
                    $jsonData = json_decode($res, true);
                    if ($jsonData['retCode'] == 'SUCCESS') {
                        if ($jsonData['payParams']['codeUrl']) {
                            $result_data['url']  = $jsonData['payParams']['codeUrl'];
                        } else {
                            return returnJson(201, '获取支付失败，请尝试使用其他方式充值！');
                        }
                    }
                }
                break;
            case 'zongyipay':
                $data = [
                    'pay_memberid' => $info['mchid'],
                    'pay_bankcode' => $info['code'],
                    'pay_orderid' => $order_no,
                    'pay_amount' => $price,
                    'pay_callbackurl' => 'https://h5.yushzg.com',
                    'pay_notifyurl' => 'https://' . $_SERVER['HTTP_HOST'] . '/api.php/notify/notifys',
                ];
                $data['pay_md5sign'] = get_sign_key($data, $info['appkey']);
                $payurl = "https://zy212.top/Pay";
                $res = curlPost($payurl, $data);
                if ($res) {
                    $jsonData = json_decode($res, true);
                    if ($jsonData['status'] == '200') {
                        if ($jsonData['data']) {
                            $result_data['url']  = $jsonData['data'];
                        } else {
                            return returnJson(201, '获取支付失败，请尝试使用其他方式充值！');
                        }
                    }
                }
                break;
            case 'fulipay':
                $data = [
                    'pay_memberid' => $info['mchid'],
                    'pay_bankcode' => $info['code'],
                    'pay_orderid' => $order_no,
                    'pay_amount' => $price,
                    'pay_callbackurl' => 'https://h5.yushzg.com',
                    'pay_notifyurl' => 'https://' . $_SERVER['HTTP_HOST'] . '/api.php/notify/notifys',
                ];
                $data['pay_md5sign'] = get_sign_key($data, $info['appkey']);
                $payurl = "https://fl222.top/Pay";
                $res = curlPost($payurl, $data);
                if ($res) {
                    $jsonData = json_decode($res, true);
                    if ($jsonData['status'] == '200') {
                        if ($jsonData['data']) {
                            $result_data['url']  = $jsonData['data'];
                        } else {
                            return returnJson(201, '获取支付失败，请尝试使用其他方式充值！');
                        }
                    }
                }
                break;
            case 'pandapay':
                $data = [
                    'amount' => (int)($price * 100),
                    'merchantNo' => $info['mchid'],
                    'channelCode' => $info['code'],
                    'merchantOrderNo' => $order_no,
                    'notifyUrl' => 'https://' . $_SERVER['HTTP_HOST'] . '/api.php/notify/pdnotify',
                    'timestamp' => time(),
                    'userId' => $user['id'],
                    'userIp' => '183.' . rand(100, 255) . '.' . rand(100, 255) . '.255',
                ];
                $data['sign'] = get_sign_md5_key($data, $info['appkey']);
                $payurl = "https://pandapay.cyou/juhe/V1.0/openapi/payment";
                $res = curlPost($payurl, $data);
                if ($res) {
                    $jsonData = json_decode($res, true);
                    if ($jsonData['code'] == 0) {
                        if ($jsonData['url']) {
                            $result_data['url']  = $jsonData['url'];
                        } else {
                            return returnJson(201, '获取支付失败，请尝试使用其他方式充值！');
                        }
                    }
                }
                break;
            case 'hongyapay':
                $data = [
                    'pay_memberid' => $info['mchid'],
                    'pay_bankcode' => $info['code'],
                    'pay_orderid' => $order_no,
                    'pay_amount' => $price,
                    'pay_callbackurl' => 'https://h5.yushzg.com',
                    'pay_notifyurl' => 'https://' . $_SERVER['HTTP_HOST'] . '/api.php/notify/notifys',
                ];
                $data['pay_md5sign'] = get_sign_key($data, $info['appkey']);
                $payurl = "https://hy113.top/Pay";
                $res = curlPost($payurl, $data);
                if ($res) {
                    $jsonData = json_decode($res, true);
                    if ($jsonData['status'] == '200') {
                        if ($jsonData['data']) {
                            $result_data['url']  = $jsonData['data'];
                        } else {
                            return returnJson(201, '获取支付失败，请尝试使用其他方式充值！');
                        }
                    }
                }
                break;
            case 'bigpandapay':
                $data = [
                    'app_id' => $info['mchid'],
                    'product_id' => $info['code'],
                    'out_trade_no' => $order_no,
                    'amount' => $price,
                    'notify_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/api.php/notify/dpdnotify',
                    'time' => time(),
                ];
                $data['sign'] = get_sign_md5_key($data, $info['appkey']);
                $payurl = "https://dfmon-api.meisuobudamiya.com/api/order";
                $res = curlPost($payurl, $data);
                if ($res) {
                    $jsonData = json_decode($res, true);
                    if ($jsonData['code'] == 200) {
                        if (isset($jsonData['data']['url'])) {
                            $result_data['url']  = $jsonData['data']['url'];
                        } else {
                            return returnJson(201, '获取支付失败，请尝试使用其他方式充值！');
                        }
                    }
                }
                break;
            case 'laicai2pay':
                // 根据接口文档构建请求参数
                $clientIp = $this->request->ip();
                if (empty($clientIp) || !filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $clientIp = '183.186.' . rand(100, 255) . '.255';
                }
                
                // 13位时间戳（毫秒）
                $reqTime = round(microtime(true) * 1000);
                
                // 构建请求参数（必填参数）
                $request_data = [
                    'mchNo' => $info['mchid'],
                    'mchOrderNo' => $order_no,
                    'productId' => $info['code'],
                    'amount' => intval($price * 100), // 金额转换为分
                    'clientIp' => $clientIp,
//                    'notifyUrl' => 'https://' . $_SERVER['HTTP_HOST'] . '/api.php/notify/laicai2notify',
                    'notifyUrl' => 'https://zhky-api-test.10293847.cc' . '/api.php/notify/laicai2notify',
                    'reqTime' => $reqTime,
                ];
                
                // 可选参数
                if (!empty($info['return_url'])) {
                    $request_data['returnUrl'] = $info['return_url'];
                } else {
                    $request_data['returnUrl'] = 'https://zhky-h5-test.10293847.cc';
                }
                
                // 可以添加扩展参数和用户ID
                $request_data['userId'] = (string)$user['id'];
                
                // 生成签名（不包含sign字段）
                $request_data['sign'] = generate_signature($request_data, $info['appkey']);
                
                $payurl = "https://laicai2-pay-api.yzzf66.com/api/pay/unifiedOrder";
                
                // 使用JSON格式发送请求
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $payurl);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json;charset=UTF-8']);
                $res = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if (!$res) {
                    return returnJson(201, '获取支付失败，请尝试使用其他方式充值！');
                }
                
                $jsonData = json_decode($res, true);
                
                // 根据接口文档处理返回结果
                // code: 0=成功, 其他失败
                if (isset($jsonData['code']) && $jsonData['code'] == 0) {
                    // 检查订单状态
                    if (isset($jsonData['data']['orderState'])) {
                        $orderState = $jsonData['data']['orderState'];
                        // 1=出码成功, 3=支付失败, 7=出码失败
                        if ($orderState == 1) {
                            // 出码成功，获取支付链接
                            if (isset($jsonData['data']['payData']) && !empty($jsonData['data']['payData'])) {
                                $result_data['url'] = $jsonData['data']['payData'];
                            } else {
                                return returnJson(201, '获取支付链接失败，请尝试使用其他方式充值！');
                            }
                        } elseif ($orderState == 3) {
                            return returnJson(201, '支付失败：' . (isset($jsonData['msg']) ? $jsonData['msg'] : '订单状态异常'));
                        } elseif ($orderState == 7) {
                            return returnJson(201, '出码失败：' . (isset($jsonData['msg']) ? $jsonData['msg'] : '订单状态异常'));
                        } else {
                            return returnJson(201, '订单状态异常：' . $orderState);
                        }
                    } else {
                        return returnJson(201, '返回数据格式异常，请尝试使用其他方式充值！');
                    }
                } else {
                    // 失败情况
                    $error_msg = '获取支付失败';
                    if (isset($jsonData['msg']) && !empty($jsonData['msg'])) {
                        $error_msg .= '：' . $jsonData['msg'];
                    } elseif (isset($jsonData['message']) && !empty($jsonData['message'])) {
                        $error_msg .= '：' . $jsonData['message'];
                    } else {
                        $error_msg .= '，错误代码：' . (isset($jsonData['code']) ? $jsonData['code'] : '未知');
                    }
                    return returnJson(201, $error_msg);
                }
                break;
            case 'bankpay':
                $result_data = [
                    'price' => $price,
                ];
                break;
        }
        if (!$result_data) {
            return returnJson(201, '获取支付失败，请尝试使用其他方式充值！');
        }
        $insert_data = [
            'recharge_type' => 1,
            'data_type' => 2,
            'order_no' => $order_no,
            'user_id' => $user['id'],
            'price' => $price,
            'status' => 3,
            'remarks' => "充值：" . $info['name'],
            'create_time' => date('Y-m-d H:i:s'),
        ];
        $insertId = Db::name('api_fund_detail')
            ->insertGetId($insert_data);
        if (!$insertId) {
            return returnJson(400, '充值失败');
        }
        if ($info['payname'] == 'bankpay') {
            $result_data['id'] = $insertId;
            $result_data['user'] = $info['code'];
            $result_data['account'] = $info['mchid'];
            $result_data['name'] = $info['appid'];
            $result_data['openname'] = $info['appkey'];
            $result_data['remarks'] = $info['remarks'];
        }
        return returnJson(200, '跳转中...', $result_data);
    }

    public function recharge_bank_apply(Request $request)
    {
        $id = $request->param('id');
        $img1 = $request->param('img1');
        if (empty($id) || empty($img1)) {
            return returnJson(400, '参数缺失');
        }
        $state = Db::name('api_fund_detail')->where('id', $id)->update([
            'img' => $img1
        ]);
        if ($state) {
            return returnJson(200, '提交成功，等待后台审核');
        }
        return returnJson(301, '提交失败，请稍后再试！');
    }

    public function recharge2(Request $request)
    {
        $user = $this->user;

        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        if (empty($user['username']) || empty($user['id_card'])) {
            return returnJson(302, '请先绑定身份证信息');
        }

        // $account = Db::name('api_account')
        //     ->where('user_id',$user['id'])
        //     ->where('is_default',1)
        //     ->where('delete_time IS NULL')
        //     ->find();
        // if (!$account){
        //     return returnJson(400,'未找到默认银行卡信息');
        // }
        // $recharge = Db::name('api_recharge')
        //     ->where('id',$request->param('bank_id'))
        //     ->where('delete_time IS NULL')
        //     ->find();
        // if (!$recharge){
        //     return returnJson(400,'未找到对应充值方式信息');
        // }

        $result = Db::name('api_fund_detail')
            ->insert([
                'data_type' => 2,
                'user_id' => $user['id'],
                'price' => $request->param('price'),
                'status' => 3,
                // 'remarks' => '打款信息: 开户名称:'.$account['account_name'].';开户账号:'.$account['account_number'].';开户行名称:'.$account['name_of_deposit_bank'].'收款信息: 收款人姓名:'.$recharge['name'].';收款银行名称:'.$recharge['bank_name'].';收款银行账号:'.$recharge['bank_account'],
                'remarks' => '用户充值',
                'create_time' => date('Y-m-d H:i:s'),
            ]);
        if (!$result) {
            return returnJson(400, '充值失败');
        }

        return returnJson(200, '等待后台审核');
    }

    public function withdrawal_page(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $account = Db::name('api_account')
            ->where('user_id', $user['id'])
            ->select();

        return returnJson(200, '成功', [
            'account' => $account,
            'price' => $user['price']
        ]);
    }

    public function transfer(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $mobile = $request->param('mobile');
        $price = $request->param('price');
        if (empty($mobile) || !is_numeric($mobile)) {
            return returnJson(400, '请输入对方手机号');
        }
        if (empty($price) || !is_numeric($price)) {
            return returnJson(400, '请输入转账金额');
        }
        $transfer_user = Db::name('api_user')->where('mobile', $mobile)->find();
        if (!$transfer_user) {
            return returnJson(400, '请输入正确的对方手机号');
        }
        if ($price > $user['price']) {
            return returnJson(400, '请输入正确的转账金额');
        }
        if (md5(md5($request->param('payword'))) !== $user['payword']) {
            return returnJson(400, '密码输入错误');
        }
        Db::startTrans();
        try {
            Db::name('api_user')->where('id', $transfer_user['id'])->update([
                'price' => Db::raw('price+' . $price)
            ]);
            Db::name('api_user')->where('id', $user['id'])->update([
                'price'        =>    Db::raw('price-' . $price)
            ]);
            $result = Db::name('api_fund_detail')->insert([
                'data_type' => 4,
                'user_id' => $user['id'],
                'price' => $price,
                'status' => 1,
                'remarks' => '转账支出' . $price,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
            $result = Db::name('api_fund_detail')->insert([
                'data_type' => 5,
                'user_id' => $transfer_user['id'],
                'price' => $price,
                'status' => 1,
                'remarks' => '转账收入' . $price,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
            Db::commit();
            return returnJson(200, '转账成功');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '提款失败');
        }
    }

    public function withdrawal(Request $request)
    {
        header('Content-Type: text/html; charset=utf-8');
        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 23:59:59');
        Db::startTrans();
        $type = $request->param('type');
        try {
            $user = $this->user;
            $__token__ = cache('token' . $user['id']);
            // if (!empty($request->param('__token__')) && $__token__ != $request->param('__token__')) {
            //     cache('token' . $user['id'], null);
            //     return returnJson(400, '您的提交太频繁');
            // } else {
            //     cache('token' . $user['id'], null);
            // }
            if (checkClicks($user['id'])) {
                return returnJson(400, '您的提交太频繁');
            }
            if (!$user) {
                return returnJson(301, '未找到当前用户请重新登录');
            }
            if ($user['status'] != 1) {
                return returnJson(400, '请实名认证后再来提现吧');
            }

            if (!preg_match('/^([1-9][0-9]*){1,10}$/', $request->param('price'))) {
                // return returnJson(400, '提现金额必须为正整数');
            }

            $setting = config('web');
            if (!empty($setting['widthdraw_except_holidays']) && (date('w', time()) == 6 || date('w', time()) == 0)) {
                return returnJson(400, '周六日不可提现');
            }
            if (!empty($setting['am_withdraw_start']) && !empty($setting['am_withdraw_end']) && !empty($setting['pm_withdraw_start']) && !empty($setting['pm_withdraw_end'])) {
                if (date('H', time()) < $setting['am_withdraw_start'] || date('H', time()) > $setting['pm_withdraw_end'] || (date('H', time()) > $setting['am_withdraw_end'] && date('H', time()) < $setting['pm_withdraw_start'])) {
                    return returnJson(400, '不在可提现时段');
                }
            }
            if (!$setting['withdraw_open'] && $user['limit_with'] == 2) {
                return returnJson(400, '当前不可提现');
            }
            //查询用户是否拥有提现权限
            // if ($user['limit_with'] == 2) {
            //     return returnJson(400, '当前不可提现');
            // }

            $account = Db::name('api_account')
                ->where('user_id', $user['id'])
                ->where('type', $type)
                ->find();
            if (empty($account)) {
                return returnJson(303, '请先绑定默认收款信息');
            }

            if (md5(md5($request->param('password'))) !== $user['payword']) {
                return returnJson(400, '密码输入错误');
            }

            if ($request->param('price') > $user['cash_price']) {
                return returnJson(400, '超出可提现余额');
            }

            //最低提现金额为100元
            if ($request->param('price') < $setting['min_withdraw']) {
                return returnJson(400, '最低提现金额为' . $setting['min_withdraw'] . '元');
            }

            //查询用户今天有没有提现过
            $withdrawal_res = Db::name('api_fund_detail')
                ->where('data_type', 1)
                ->where('user_id', $user['id'])
                ->whereTime('create_time', 'between', [$start, $end])
                ->find();
            if (!empty($withdrawal_res)) {
                return returnJson(400, '每天只能提现一次');
            }

            if ($request->param('price') > $setting['today_withdraw']) {
                return returnJson(400, '每日提现金额为' . $setting['today_withdraw'] . '元');
            }

            if ($setting['receive_open']) {
                $incomePay = Db::name('api_user_receive')->where('user_id', $user['id'])->where('income_status', 2)->count();
                if (!$incomePay) {
                    return returnJson(405, '请先缴纳个人所得税再提现！');
                }
            }

            if ($setting['apply_bank_open']) {
                $applyBank = Db::name('api_user_apply_bank')->where('user_id', $user['id'])->where('is_delete',0)->find();
                if (!$applyBank) {
                    return returnJson(406, '请先办理专属银行卡！');
                }
                if ($applyBank){
                    $msg = '';
                    switch ($applyBank['bank_status']) {
                        case '1':
                            $msg = '办理成功，等待审核中...';
                            break;
                        case '2':
                            $msg = '审核成功，正在制卡中...';
                            break;
                    }
                    return returnJson(406, $msg);
                }
            }
            $result = Db::name('api_user')
                ->where('id', $user['id'])
                ->dec('cash_price', (float)$request->param('price'))
                ->update();
            if (!$result) {
                return returnJson(400, '金额扣除失败');
            }
            if ($type) {
                $remarks = '支付宝姓名:' . $account['account_name'] . ';支付宝账号:' . $account['account_number'];
            } else {
                $remarks = '开户名称:' . $account['account_name'] . ';开户账号:' . $account['account_number'] . ';开户行名称:' . $account['name_of_deposit_bank'];
            }
            $result = Db::name('api_fund_detail')
                ->insert([
                    'order_no' => 'DF' . date('Ymd') . rand(100000, 999999),
                    'type' => $request->param('type'),
                    'data_type' => 1,
                    'user_id' => $user['id'],
                    'price' => $request->param('price'),
                    'status' => 3,
                    'before' => $user['cash_price'],
                    'after'  => $user['cash_price'] - $request->param('price'),
                    'remarks' => $remarks,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            if (!$result) {
                return returnJson(400, '提款失败');
            }

            Db::commit();
            return returnJson(200, '提交成功等待后台审核');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '提款失败');
        }
    }

    public function fund_detail_list(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $data_type = $request->param('data_type');
        if ($data_type) {
            if ($data_type == 20) {
                $type = [20, 27];
            } else {
                $type = [$data_type];
            }
        } else {
            $type = [1, 2, 3, 4, 5, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30 ,31];
        }
        $fund_detail = Db::name('api_fund_detail')
            ->where('user_id', $user['id'])
            ->where('remarks', 'not like', '充值：%')
            ->where('data_type', 'in', $type)
            ->where('status', 'in', [1, 2, 3]) // status = 1：成功 status = 2：拒绝 // status = 3（或其他值）：待审核
            ->order('create_time desc,id desc')
            ->where('delete_time IS NULL');
        $count = (clone $fund_detail)->count();
        $fund_detail = $fund_detail->page((int)$request->param('page', 1), (int)$request->param('page_size', 10))->filter(function ($fund_detail) {
            // $fund_detail['create_time'] = date('Y-m-d',strtotime($fund_detail['create_time']));
            $fund_detail['type_text'] = $this->get_type_text($fund_detail);
            if ((strpos($fund_detail['remarks'], '资格券') !== false)) {
                $fund_detail['price'] = "--";
            }
            return $fund_detail;
        })->select();
        return returnJson(200, '成功', [
            'data' => $fund_detail,
            'count' => $count
        ]);
    }

    public function get_type_text($item)
    {
        $recharge_type = [
            "系统",
            "充值",
            "任务返现",
            "积分兑换",
        ];
        $data_type = get_data_type();
        if ($item['data_type'] == 2) {
            return $item['node'] ? $item['node'] : $recharge_type[$item['recharge_type']];
        }
        if ($item['data_type'] == 16) {
            return (strpos($item['remarks'], '日收益') !== false) ? '每日收益' : $data_type[$item['data_type']];
        }
        if ($item['data_type'] == 25) {
            return $item['node'] ? $item['node'] : $item['remarks'];
        } else {
            return $item['node'] ? $item['node'] : ((strpos($item['remarks'], '资格券') !== false) ? $data_type['99'] : $data_type[$item['data_type']]);
        }
    }

    public function share(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        return returnJson(200, '成功', [
            'download_url' => config('web')['download_url'],
            'reg_url' => config('web')['reg_url'],
            'code' => encode_Invite($user['id']),
        ]);
    }

    //档次
    public function receive_cate(Request $request)
    {
        $list = get_receive_cates();
        $__token__ = md5(time() . rand(100000, 999999));
        return returnJson(200, '成功', [
            'data' => $list,
            '__token__' => $__token__
        ]);
    }

    //所得税是否缴纳
    public function receive_record(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $receice = Db::name('api_user_receive')->where('user_id', $user['id'])->filter(function ($receice) {
            $receice['create_time'] = $receice['create_time'] ? date('Y-m-d', strtotime($receice['create_time'])) : '';
            $receice['update_time'] = $receice['update_time'] ? date('Y-m-d', strtotime($receice['update_time'])) : '';
            return $receice;
        })->find();

        //获取收益
        $receice_info = get_fun_income_profit($user['huimin_price']);

        $__token__ = md5(time() . rand(100000, 999999));
        $result = ['count' => $receice ? 1 : 0, 'info' => $receice ?? [], 'receice' => $receice_info];
        return returnJson(200, '成功', [
            'data' => $result,
            '__token__' => $__token__
        ]);
    }


    //领取详情
    public function receive_info(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }

        $id = $request->param('id');
        if (empty($id)) {
            return returnJson(400, '参数缺失');
        }
        $receice = Db::name('api_user_receive')->where('id', $id)->field('id,certify_status,certify_audit,certify_image,income_status,income_audit,income_image,handle_status')->find();
        $__token__ = md5(time() . rand(100000, 999999));
        return returnJson(200, '成功', [
            'data' => $receice,
            '__token__' => $__token__
        ]);
    }

    public function receive_pay(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            Db::rollback();
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $cate_id = $request->param('cate_id');
        $amount = $request->param('amount');
        $step = $request->param('step');
        // $receive_id = $request->param('receive_id');
        $pay_password = $request->param('password');
        if (!$pay_password) {
            return returnJson(400, '请输入资金密码');
        }
        $user = Db::name('api_user')->where('id', $user['id'])->find();
        if ($user['status'] != 1) {
            return returnJson(400, '未通过实名认证不能缴纳');
        }
        if (!$user['payword']) {
            return returnJson(400, '请先设置资金密码');
        }
        if (md5(md5($pay_password)) != $user['payword']) {
            return returnJson(400, '资金密码错误，请重新输入！');
        }
        //获取收益
        $receice_info = get_fun_income_profit($user['huimin_price']);
        if (!$receice_info) {
            return returnJson(400, '暂未达到缴纳的条件！');
        }
        $payMoney = $receice_info['profit'];
        if ($amount != $payMoney) {
            return returnJson(400, '缴纳金额错误');
        }
        if (($user['price'] - $request->param('amount')) < 0) {
            return returnJson(212, '账户余额不足');
        }
        Db::startTrans();
        try {
            // $maxid = intval(Db::name('api_user_receive')->max('id'));
            $data = [
                // 'id' => $maxid + 1,
                'user_id' => $user['id'],
                'name' => $user['username'],
                // 'sex' => $user['sex'] ? '女' : '男',
                'idcard' => $user['id_card'],
                'money' => sprintf("%.2f", $amount),
                'money_text' => numberToChinese($amount)
            ];
            $result = [];
            $image = $this->get_judicial_image($data, 1);
            if ($step) {
                $insert_array = [
                    'name' => $receice_info['name'],
                    'user_id' => $user['id'],
                    'cate_id' => $cate_id,
                    'income_price' => $payMoney,
                    'income_status'    => 1,
                    'income_image' => $image,
                    'create_time' => date('Y-m-d H:i:s'),
                ];
                $id = Db::name('api_user_receive')->insertGetId($insert_array);
                if ($id) {
                    if ($user['price'] >= $amount) {
                        Db::name('api_user')->where('id', $user['id'])->dec('price', (float)($amount))->update();
                    }
                    $result['id'] = $id;
                    Db::name('api_fund_detail')->insert([
                        'data_type' => 23,
                        'user_id' => $user['id'],
                        'price' => (float)$amount,
                        'status' => 1,
                        'remarks' => '个人所得税->' . $id,
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            if (!$id) {
                Db::rollback();
                return returnJson(400, '处理异常');
            }
            Db::commit();
            return returnJson(200, '缴纳成功，请等待审核！');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '缴纳失败!' . $e->getMessage() . $e->getLine());
        }
    }

    //养老金领取
    public function pension_profit()
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        // if (cache('token_pen_profit_' . $user['id'])) {
        //     cache('token_pen_profit_' . $user['id'], null);
        //     return returnJson(400, '您的提交太频繁');
        // } else {
        //     $__token__ = 'token_pen_profit_' . md5(time() . rand(100000, 999999));
        //     cache('token_pen_profit_' . $user['id'], $__token__);
        // }
        if (checkClicks($user['id'])) {
            return returnJson(400, '您的提交太频繁');
        }
        $amount = $user['pension_price'];
        //判断是否领取
        $count = Db::name('api_fund_detail')->whereDay('create_time')->where('data_type', 27)->where('user_id', $user['id'])->count();
        if ($count) {
            return returnJson(400, '今日收益已领取，请明日再来！');
        }
        //获取收益
        $profit_price = get_fun_pension_profit($amount);
        if (!$profit_price) {
            return returnJson(400, '抱歉，暂未达到领取的条件！');
        }
        Db::startTrans();
        try {
            // 用户返利
            // Db::name('api_user')->where('id', $user['id'])
            //     ->inc('price', (float)$profit_price)
            //     ->update();
            // Db::name('api_fund_detail')->insert([
            //     'data_type'     => 27,
            //     'user_id' => $user['id'],
            //     'price' => $profit_price,
            //     'status' => 1,
            //     'remarks' => '养老金收益:' . $profit_price,
            //     'create_time' => date('Y-m-d H:i:s'),
            // ]);

            $model = Db::name('api_voucher')->where('amount', $profit_price)->find();
            if (!$model) {
                return returnJson(400, '领取失败，请稍后再试！');
            }
            Db::name('api_benefits')->insert([
                'user_id' => $user['id'],
                'type' => 0,
                'voucher_id' => $model['id'],
                'amount' => $model['amount'],
                'addtime' => strtotime($model['starttime']),
                'invalidtime' => strtotime($model['endtime']),
            ]);

            Db::name('api_fund_detail')
                ->insert([
                    'data_type' => 27,
                    'recharge_type' => 0,
                    'user_id' => $user['id'],
                    'price' => (float) $profit_price,
                    'node' => '',
                    'status' => 1,
                    'remarks' => $user['id'] . "：养老金领取代金券一张",
                    'create_time' => date('Y-m-d H:i:s'),
                ]);

            cache('token_pen_profit_' . $user['id'], null);
            Db::commit();
            return returnJson(200, '领取成功');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '领取失败!' . $e->getMessage() . $e->getLine());
        }
    }

    //钱包收益领取
    public function wallet_profit()
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        // if (cache('token_wallet_profit_' . $user['id'])) {
        //     cache('token_wallet_profit_' . $user['id'], null);
        //     return returnJson(400, '您的提交太频繁');
        // } else {
        //     $__token__ = 'token_wallet_profit_' . md5(time() . rand(100000, 999999));
        //     cache('token_wallet_profit_' . $user['id'], $__token__);
        // }
        if (checkClicks($user['id'])) {
            return returnJson(400, '您的提交太频繁');
        }
        $amount = $user['huimin_price'];
        //判断是否领取
        $count = Db::name('api_fund_detail')->whereDay('create_time')->where('data_type', 29)->where('user_id', $user['id'])->count();
        if ($count) {
            return returnJson(400, '今日收益已领取，请明日再来！');
        }
        //获取收益
        $profit_price = get_fun_wallet_profit($amount);
        Db::startTrans();
        try {
            $huimin_price = Db::name('api_user')->where('id', $user['id'])->value('huimin_price');
            // $profit_price = $huimin_price>=$profit_price ? $profit_price : $huimin_price;
            $profit_price = min($profit_price, $huimin_price);
            Db::name('api_fund_detail')
                ->insert([
                    'data_type' => 29,
                    'recharge_type' => 0,
                    'user_id' => $user['id'],
                    'price' => (float) $profit_price,
                    'node' => '',
                    'status' => 1,
                    'remarks' => $user['id'] . "：钱包收益{$profit_price}元",
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
            Db::name('api_user')->where('id', $user['id'])->dec('huimin_price', (float)($profit_price))->inc('cash_price', (float)($profit_price))->update();
            cache('token_wallet_profit_' . $user['id'], null);
            Db::commit();
            return returnJson(200, '领取成功');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '领取失败!' . $e->getMessage() . $e->getLine());
        }
    }

    //项目收益领取
    public function subscribe_profit(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $id = $request->param('id');
        if (!$id) {
            return returnJson(400, '参数缺失或参数错误');
        }
        $info = Db::name('api_subscribe')
            ->leftJoin('api_product ap', 'ap.id=as.product_id')
            ->alias('as')->where('as.status', '<>', 3)->where('as.id', $id)->field('as.*,ap.cycle_num')->find();
        if (!$info) {
            return returnJson(400, '项目不存在或已结束！');
        }
        if (checkClicks($user['id'])) {
            return returnJson(400, '您的提交太频繁');
        }
        $timeline_type = $info['timeline_type'];
        switch ($info['timeline_type']) {
            case 3:
            case 4:
            case 5:
                $end_time =  date('Y-m-d', strtotime($info['end_time']));
                // $timesDay = $timeline_type == 3 ? 5 : 365;
                $timesDay = in_array($timeline_type, [3, 5]) ? $info['cycle_num'] : 365;
                if ($info['times'] >= $timesDay) {
                    return returnJson(400, '订单已结束！');
                }
                if (in_array($timeline_type, [3, 5])) {
                    if (time() < strtotime($end_time)) {
                        return returnJson(400, "请您" . date('m月d日', strtotime($end_time)) . ",再来领取！");
                    }
                } else {
                    if (date('Ymd', strtotime($info['create_time'])) == date('Ymd', time())) {
                        return returnJson(400, '抱歉，请明日再领取！');
                    }
                    if (time() < strtotime($end_time)) {
                        return returnJson(400, '今日收益已领取，请明日再来！');
                    }
                }
                Db::startTrans();
                try {
                    // 修改产品状态
                    $day = in_array($timeline_type, [3, 5]) ? $info['cycle'] : "1";
                    $isover = $info['times'] >= intval($timesDay - 1) ? true : false;
                    Db::name('api_subscribe')->where('id', $id)->update([
                        'end_time' => date('Y-m-d H:i:s', strtotime("+{$day} day", strtotime(date('Y-m-d', time())))),
                        'update_time' => date('Y-m-d H:i:s'),
                        'status' => $isover ? 3 : 1,
                        'times' => Db::raw('times+1')
                    ]);
                    $product = Db::name('api_product')->where('id', $info['product_id'])->field('id,is_new,price,income')->find();
                    // $field = $product['is_new'] ? 'cash_price' : 'withdraw_price';
                    $field = 'cash_price';
                    if (!$product['price']) {
                        return;
                    }
                    if ($isover) {
                        $estimated_total_revenue = floatval($info['price'] + $product['income']);
                    } else {
                        $estimated_total_revenue = $product['income'];
                    }
                    if ($estimated_total_revenue) {
                        // 用户返利
                        Db::name('api_user')->where('id', $user['id'])
                            ->inc($field, (float)$estimated_total_revenue)
                            ->update();
                        // 用户返利增加流水
                        $remarks = $product['id'] . '|理财到期返利:' . $info['order_number'];
                        if ($timeline_type == 4) {
                            $remarks = $product['id'] . '|理财日收益:' . $info['order_number'];
                        }
                        Db::name('api_fund_detail')->insert([
                            'data_type' => 16,
                            'user_id' => $user['id'],
                            'price' => $product['income'],
                            'status' => 1,
                            'remarks' => $remarks,
                            'create_time' => date('Y-m-d H:i:s'),
                        ]);
                        if ($isover) {
                            // 用户返本增加流水
                            Db::name('api_fund_detail')->insert([
                                'data_type' => 17,
                                'user_id' => $user['id'],
                                'price' => $info['price'],
                                'status' => 1,
                                'remarks' => $product['id'] . '|理财到期返本:' . $info['order_number'],
                                'create_time' => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                    Db::commit();
                    return returnJson(200, '领取成功');
                } catch (\Exception $e) {
                    Db::rollback();
                    return returnJson(400, '领取失败!' . $e->getMessage() . $e->getLine());
                }
                break;
            default:
                //判断是否领取
                $count = Db::name('api_subscribe')->whereDay('update_time')->where('id', $id)->count();
                if ($count) {
                    return returnJson(400, '今日收益已领取，请明日再来！');
                }
                if (date('Ymd', strtotime($info['create_time'])) == date('Ymd', time())) {
                    return returnJson(400, '抱歉，请明日再领取！');
                }
                Db::startTrans();
                try {
                    // 修改产品状态
                    Db::name('api_subscribe')->where('id', $id)->update([
                        'end_time' => date('Y-m-d H:i:s', strtotime("+1 day", strtotime($info['end_time']))),
                        'update_time' => date('Y-m-d H:i:s', time()),
                        'times' => Db::raw('times+1')
                    ]);
                    $product = Db::name('api_product')->where('id', $info['product_id'])->field('id,is_new,price,income')->find();
                    // $field = $product['is_new'] ? 'cash_price' : 'withdraw_price';
                    $field = 'cash_price';
                    if (!$product['price']) {
                        return;
                    }
                    $estimated_total_revenue = $product['income'];
                    if ($estimated_total_revenue) {
                        // 用户返利
                        Db::name('api_user')->where('id', $user['id'])
                            ->inc($field, (float)$estimated_total_revenue)
                            ->update();
                        // 用户返利增加流水
                        Db::name('api_fund_detail')->insert([
                            'data_type' => 16,
                            'user_id' => $user['id'],
                            'price' => $estimated_total_revenue,
                            'status' => 1,
                            'remarks' => $product['id'] . '|理财日收益:' . $info['order_number'],
                            'create_time' => date('Y-m-d H:i:s'),
                        ]);
                    }
                    Db::commit();
                    return returnJson(200, '领取成功');
                } catch (\Exception $e) {
                    Db::rollback();
                    return returnJson(400, '领取失败!' . $e->getMessage() . $e->getLine());
                }
                break;
        }
    }


    public function pension_exchange(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $amount = $request->param('amount');
        $pay_password = $request->param('password');
        if (!$pay_password) {
            return returnJson(400, '请输入资金密码');
        }
        $user = Db::name('api_user')->where('id', $user['id'])->find();
        if ($user['status'] != 1) {
            return returnJson(400, '未通过实名认证不能缴纳');
        }
        if (!$user['payword']) {
            return returnJson(400, '请先设置资金密码');
        }
        if (md5(md5($pay_password)) != $user['payword']) {
            return returnJson(400, '资金密码错误，请重新输入！');
        }
        if ($amount > $user['pension_price']) {
            return returnJson(400, '养老金余额不足');
        }
        $coupon_total = (new ApiSubscribe())->getCouponTotal($user);
        if ($coupon_total < $user['pension_price']) {
            return returnJson(400, '兑换券总额度小于养老金，不能兑换！');
        }
        $list = (new ApiSubscribe())->getCouponList($user);
        // $state = Db::name('api_user')->where('id', $user['id'])->dec('pension_price', (float)$amount)->inc('huimin_price', (float)$amount)->update();
        $state = Db::name('api_user')->where('id', $user['id'])->dec('pension_price', (float)$amount)->update();
        if ($state) {
            $total = 0;
            $ids = [];
            foreach ($list as $val) {
                $total += $val['coupon_price'];
                if ($total >= $amount) {
                    $ids[] = $val['id'];
                    break;
                } else {
                    $ids[] = $val['id'];
                }
            }
            if (count($ids)) {
                Db::name('api_subscribe')->whereIn('id', $ids)->data(['update_time' => date('Y-m-d H:i:s', time())])->update();
                Db::name('api_fund_detail')->insert([
                    'data_type' => 25,
                    'user_id' => $user['id'],
                    'price' => (float)$amount,
                    'eids' => implode(",", $ids),
                    'status' => 3,
                    'remarks' => '养老金兑换养老钱包',
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
                return returnJson(200, '申请成功，等待审核！');
            }
            return returnJson(400, '可用兑换券不足！');
        }
        return returnJson(400, '兑换失败！');
    }

    public function apply_bank(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            Db::rollback();
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $bank_id = $request->param('bank_id', 0);
        $amount = $request->param('price');
        $pay_password = $request->param('password');
        if (!$pay_password) {
            return returnJson(400, '请输入资金密码');
        }
        if (checkClicks($user['id'])) {
            return returnJson(400, '您的提交太频繁');
        }
        $user = Db::name('api_user')->where('id', $user['id'])->find();
        if ($user['status'] != 1) {
            return returnJson(400, '未通过实名认证不能缴纳');
        }
        if (!$user['payword']) {
            return returnJson(400, '请先设置资金密码');
        }
        if (md5(md5($pay_password)) != $user['payword']) {
            return returnJson(400, '资金密码错误，请重新输入！');
        }
        $count = Db::name('api_user_apply_bank')->where('user_id', $user['id'])->where('is_delete',0)->count();
        if ($count) {
            return returnJson(400, '已办理过银行卡，请勿重复办理！');
        }
        $bank_info = get_fun_apply_bank_profit($user['cash_price']);
        if (!$bank_info) {
            return returnJson(400, '暂未达到申请条件！');
        }
        $payMoney = $bank_info['profit'];
        if ($amount != $payMoney) {
            return returnJson(400, '办理金额错误');
        }
        if (($user['price'] - $amount) < 0) {
            return returnJson(212, '账户余额不足');
        }
        Db::startTrans();
        try {
            $result = [];
            $insert_array = [
                'name' => $bank_info['name'],
                'user_id' => $user['id'],
                'bank_id' => $bank_id,
                'bank_price' => $payMoney,
                'bank_status'    => 1,
                'bank_image' => $bank_info['image'],
                'create_time' => date('Y-m-d H:i:s'),
            ];
            $id = Db::name('api_user_apply_bank')->insertGetId($insert_array);
            if ($id) {
                if ($user['price'] >= $amount) {
                    Db::name('api_user')->where('id', $user['id'])->dec('price', (float)($amount))->update();
                }
                $result['id'] = $id;
                Db::name('api_fund_detail')->insert([
                    'data_type' => 31,
                    'user_id' => $user['id'],
                    'price' => (float)$amount,
                    'status' => 1,
                    'remarks' => '办理银行卡->' . $id,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
                // Db::name('api_user_apply_bank')->where('user_id',$user['id'])->where('is_delete',1)->delete(true);
            }
            if (!$id) {
                Db::rollback();
                return returnJson(400, '处理异常');
            }
            Db::commit();
            return returnJson(200, '办理成功，请等待审核！');
        } catch (\Exception $e) {
            Db::rollback();
            return returnJson(400, '办理失败!' . $e->getMessage() . $e->getLine());
        }
    }

    public function huimin_apply(Request $request)
    {
        $user = $this->user;
        $pay_password = $request->param('pass');
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        if (!$user['payword']) {
            return returnJson(400, '请先设置资金密码');
        }
        if (!$pay_password) {
            return returnJson(400, '参数缺失或参数错误');
        }
        if (md5(md5($pay_password)) != $user['payword']) {
            return returnJson(400, '资金密码错误，请重新输入！');
        }
        if ($user['status'] != 1) {
            return returnJson(400, '未通过实名认证不能申请！');
        }
        $is_active = Db::name('api_subscribe')
            ->where('user_id', $user['id'])
            // ->whereIn('product_id', [42, 51])
            ->count();
        if (!$is_active) {
            return returnJson(400, '账号未激活,请激活后再申请!');
        }
        $apply_status = Db::name('api_user')->where('id', $user['id'])->value('huimin_apply');
        if ($apply_status == 1) {
            return returnJson(200, '已申请，请等待后台审核');
        }
        if ($apply_status == 2) {
            return returnJson(200, '已成功申请');
        }
        Db::name('api_user')->where('id', $user['id'])->update(['huimin_apply' => 1, 'apply_time' => date('Y-m-d H:i:s')]);
        return returnJson(200, '申请成功');
    }

    public function month_apply(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $id = $request->param('id');
        if (empty($id)) {
            return returnJson(400, '参数缺失');
        }
        $type = $request->param('type');
        if (!is_numeric($type)) {
            return returnJson(400, '参数缺失');
        }
        $pay_password = $request->param('password');
        if (!$pay_password) {
            return returnJson(400, '请输入资金密码');
        }
        $user = Db::name('api_user')->where('id', $user['id'])->find();
        if ($user['status'] != 1) {
            return returnJson(400, '未通过实名认证不能缴纳');
        }
        if (!$user['payword']) {
            return returnJson(400, '请先设置资金密码');
        }
        if (md5(md5($pay_password)) != $user['payword']) {
            return returnJson(400, '资金密码错误，请重新输入！');
        }
        $count = Db::name('api_subscribe')->where('id', $id)->where('month_status', '>', 0)->count();
        if ($count) {
            return returnJson(400, '已申请过福利，请勿重复申请！');
        }
        $state = Db::name('api_subscribe')->where('id', $id)->data(['month_status' => 1, 'month_type' => $type])->update();
        if ($state) {
            return returnJson(200, '申请成功！');
        }
        return returnJson(400, '申请失败！');
    }

    public function subscribe_info(Request $request)
    {
        $user = $this->user;
        if (!$user) {
            return returnJson(301, '未找到当前用户请重新登录');
        }
        $id = $request->param('id');
        if (empty($id)) {
            return returnJson(400, '参数缺失');
        }
        $subscribe = Db::name('api_subscribe')->where('id', $id)->field('id,insurance_image')->find();
        $__token__ = md5(time() . rand(100000, 999999));
        return returnJson(200, '成功', [
            'data' => $subscribe,
            '__token__' => $__token__
        ]);
    }

    public function pension_apply_save(Request $request)
    {
        $user = $this->user;
        if (!@preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9]+$/u', $request->param('username'))) {
            return returnJson(400, '非法操作');
        }
        if (!@preg_match("/^[xX0-9]+$/u", $request->param('id_card')) || strlen($request->param('id_card')) < 15) {
            return returnJson(400, '请填写正确的身份证号');
        }
        // if ($this->checkIdCard($request->param('id_card')) !== true) {
        //     return returnJson(400, '请填写正确的身份证号');
        // }
        $r = "/http[s]?:\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is";
        if (!@preg_match($r, $request->param('img1'))) {
            return returnJson(400, '非法操作');
        }
        if (!@preg_match($r, $request->param('img2'))) {
            return returnJson(400, '非法操作');
        }
        if (!@preg_match($r, $request->param('img3'))) {
            return returnJson(400, '非法操作');
        }
        $is_apply = Db::name('api_user_pension')
            ->where('user_id', $user['id'])
            ->whereIn('status', [1, 3])
            ->count();
        if ($is_apply) {
            return returnJson(400, '每个账号只能申请一次！');
        }
        $data = array_merge(
            $request->only(['username', 'id_card', 'sex', 'img1', 'img2', 'img3']),
            [
                'user_id'     => $user['id'],
                'status'      => 3,
                'create_time' => date('Y-m-d H:i:s')
            ]
        );
        Db::name('api_user_pension')->save($data);
        return returnJson(200, '申请成功');
    }

    public function get_insurance_image($data, $flag = 0)
    {
        $font_path = public_path() . 'static/msyh.ttc';
        $image = imagecreatefromjpeg(public_path() . 'static/policy.jpg');
        $text_color0 = imagecolorallocate($image, 255, 255, 255); // 文字
        $text_color = imagecolorallocate($image, 51, 51, 51); // 文字
        // 添加文字到图片上
        imagettftext($image, 30, 0, 300, 70, $text_color0, $font_path, $data['pname']);
        imagettftext($image, 15, 0, 605, 128, $text_color, $font_path, 'BD' . date('Ymd') . str_pad((string) $data['id'], 4, "0", STR_PAD_LEFT));
        imagettftext($image, 16, 0, 142, 260, $text_color, $font_path, $data['name']);
        imagettftext($image, 16, 0, 582, 261, $text_color, $font_path, $data['idcard']);
        imagettftext($image, 16, 0, 82, 378, $text_color, $font_path, $data['name']);
        imagettftext($image, 16, 0, 392, 377, $text_color, $font_path, $data['sex'] . '性');
        imagettftext($image, 16, 0, 432, 425, $text_color, $font_path, $data['idcard']);
        imagettftext($image, 16, 0, 82, 585, $text_color, $font_path, '法定继承人');
        imagettftext($image, 17, 0, 16, 748, $text_color, $font_path, '生活保障');
        imagettftext($image, 16, 0, 142, 748, $text_color, $font_path, $data['psname']);
        imagettftext($image, 16, 0, 142, 795, $text_color, $font_path, $data['pcode']);
        imagettftext($image, 16, 0, 142, 843, $text_color, $font_path, $data['pmanager']);
        imagettftext($image, 16, 0, 292, 1000, $text_color, $font_path, $data['money_text']);
        imagettftext($image, 16, 0, 568, 998, $text_color, $font_path, (string) $data['money']);
        //输出图像
        $image_path = '/upload/subscribe/' . $data['user_id'] . '_' . $data['id'] . '.jpg';
        imagepng($image, public_path() . $image_path);
        imagedestroy($image);
        return $image_path;
    }

    public function get_judicial_image($data, $flag = 0)
    {
        $font_path = public_path() . 'static/msyh.ttc';
        if ($flag) {
            $image = imagecreatefromjpeg(public_path() . 'static/tax.jpg');
            $text_color = imagecolorallocate($image, 0, 0, 0);
            // 添加文字到图片上
            imagettftext($image, 16, 0, 178, 198, $text_color, $font_path, $data['name']);
            imagettftext($image, 16, 0, 178, 244, $text_color, $font_path, '居民身份证');
            imagettftext($image, 16, 0, 178, 292, $text_color, $font_path, $data['idcard']);
            imagettftext($image, 16, 0, 138, 435, $text_color, $font_path, date('Y.m.d'));
            imagettftext($image, 16, 0, 378, 435, $text_color, $font_path, $data['money']);
            imagettftext($image, 16, 0, 748, 435, $text_color, $font_path, date('Y.m'));
            imagettftext($image, 16, 0, 538, 482, $text_color, $font_path, $data['money_text']);
            //输出图像
            $image_path = '/upload/receive/' . $data['user_id'] . '_2.jpg';
        } else {
            $image = imagecreatefromjpeg(public_path() . 'static/judicial.jpg');
            $text_color = imagecolorallocate($image, 17, 71, 147);
            // 添加文字到图片上
            imagettftext($image, 13, 0, 590, 424, $text_color, $font_path, 'SD' . date('Y') . str_pad((string) $data['id'], 4, "0", STR_PAD_LEFT));
            imagettftext($image, 13, 0, 200, 502, $text_color, $font_path, $data['name']);
            imagettftext($image, 13, 0, 200, 547, $text_color, $font_path, $data['sex']);
            imagettftext($image, 13, 0, 200, 587, $text_color, $font_path, $data['idcard']);
            imagettftext($image, 17, 0, 215, 749, $text_color, $font_path, $data['money_text']);
            imagettftext($image, 17, 0, 215, 798, $text_color, $font_path, $data['money']);
            //输出图像
            $image_path = '/upload/receive/' . $data['user_id'] . '_1.jpg';
        }
        imagepng($image, public_path() . $image_path);
        imagedestroy($image);
        return $image_path;
    }
}
