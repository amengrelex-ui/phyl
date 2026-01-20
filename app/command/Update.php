<?php

declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class Update extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('update')
            ->setDescription('定时计划：更新下级用户');
    }

    protected function execute(Input $input, Output $output)
    {
        $admins = Db::name('admin_admin')->where('admin_bind_id', '>', 0)->field('id,admin_bind_id,subtotal')->order('group asc')->select();
        if (count($admins)) {
            Db::startTrans();
            try {
                foreach ($admins as $admin) {
                    $subs = array_unique(self::getAllSubUserIds($admin['admin_bind_id']));
                    if (count($subs) != $admin['subtotal']) {
                        $substr =  implode(',', $subs);
                        Db::name('admin_admin')->where('id', $admin['id'])->update([
                            'subs' => $substr,
                            'subtotal' => count($subs)
                        ]);
                    }
                    if (count($subs)) {
                        self::updateDataTotal($admin['id']);
                    }
                }
                Db::commit();
                $output->writeln('更新成功');
            } catch (\Exception $e) {
                Db::rollback();
                $output->writeln('更新失败' . $e->getMessage() . $e->getLine());
            }
        }
    }

    public static function getAllSubUserIds($userId, &$subs = [], &$processed = [])
    {
        if (in_array($userId, $processed)) {
            return $subs;
        }
        $processed[] = $userId;
        $users = Db::name('api_user')->where('parent_user_id', $userId)->select();
        foreach ($users as $user) {
            $subs[] = $user['id'];
            self::getAllSubUserIds($user['id'], $subs, $processed);
        }
        return $subs;
    }

    public static function updateDataTotal($id)
    {
        $time = time();
        // 昨天时间
        $yesterday_start_time = date('Y-m-d ', $time - 86400) . '00:00:00';
        $yesterday_end_time = date('Y-m-d ', $time - 86400) . '23:59:59';
        // 今天时间
        $item_start_time = date('Y-m-d ') . '00:00:00';
        $item_end_time = date('Y-m-d ') . '23:59:59';
        $admin = Db::name('admin_admin')->where('id', $id)->field('id,admin_bind_id,subs')->find();


        $yesterday_user_count = Db::name('api_user')
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->when(true, function ($query) use ($admin) {
                $query->whereIn('id', $admin['subs']);
            })
            ->count();
        //今天
        $item_user_count = Db::name('api_user')
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('id', $admin['subs']);
                }
            })
            ->count();
        //所有
        $all_user_count = Db::name('api_user')
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('id', $admin['subs']);
                }
            })
            ->count();
        // 手动充值数量
        //昨天
        $yesterday_manual_count = Db::name('api_fund_detail')
            ->where('data_type', 2)
            ->where('status', 1)
            ->where('order_no', '=', null)
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->where('create_time', '>=', '2025-01-06 00:00:00')
            ->where("remarks", "notlike", "%-%")
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //今天
        $item_manual_count = Db::name('api_fund_detail')
            ->where('data_type', 2)
            ->where('status', 1)
            ->where('order_no', '=', null)
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->where('create_time', '>=', '2025-01-06 00:00:00')
            ->where("remarks", "notlike", "%-%")
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //所有
        $all_manual_count = Db::name('api_fund_detail')
            ->where('data_type', 2)
            ->where('order_no', '=', null)
            ->where('create_time', '>=', '2025-01-06 00:00:00')
            ->where("remarks", "notlike", "%-%")
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->where('status', 1)
            ->sum('price');


        //每日提现数量
        //昨天
        $yesterday_price_count = Db::name('api_fund_detail')
            ->where('data_type', 1)
            ->where('status', 1)
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');

        //今天
        $item_price_count = Db::name('api_fund_detail')
            ->where('data_type', 1)
            ->where('status', 1)
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //所有
        $all_price_count = Db::name('api_fund_detail')
            ->where('data_type', 1)
            ->where('status', 1)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->count();

        // 会员投资金额
        //昨天
        $yesterday_investment_count = Db::name('api_subscribe')
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->where('delete_time', '0000-00-00 00:00:00')
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->sum('price');

        //今天
        $item_investment_count = Db::name('api_subscribe')
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->where('delete_time', '0000-00-00 00:00:00')
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->sum('price');

        //所有
        $all_investment_count = Db::name('api_subscribe')
            ->where('delete_time', '0000-00-00 00:00:00')
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->sum('price');


        // 充值总数量
        //昨天
        $yesterday_recharge_count = Db::name('api_fund_detail')
            ->where('data_type', 2)
            ->where('status', 1)
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //今天
        $item_recharge_count = Db::name('api_fund_detail')
            ->where('data_type', 2)
            ->where('status', 1)
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //总计
        $all_recharge_count = Db::name('api_fund_detail')
            ->where('data_type', 2)
            ->where('status', 1)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->sum('price');
        // 会员总余额
        $user_total_price = Db::name('api_user')
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('id', $admin['subs']);
                }
            })
            ->sum('price');

        $item_user_status_count = Db::name('api_user')
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->where('status', 1)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('id', $admin['subs']);
                }
            })
            ->count();
        $yestoday_user_status_count = Db::name('api_user')
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->where('status', 1)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('id', $admin['subs']);
                }
            })
            ->count();

        $user_open_wallet = Db::name('api_user')
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->where('huimin_apply', 2)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('id', $admin['subs']);
                }
            })
            ->count();
        $user_yestoday_open_wallet = Db::name('api_user')
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->where('huimin_apply', 2)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('id', $admin['subs']);
                }
            })
            ->count();
        $user_open_wallet_total = Db::name('api_user')
            ->where('huimin_apply', 2)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('id', $admin['subs']);
                }
            })
            ->count();

        $total_user_status_count = Db::name('api_user')
            ->where('status', 1)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('id', $admin['subs']);
                }
            })
            ->count();

        $item_user_active_count = Db::name('api_subscribe')
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->where('status', 1)
            ->whereIn('product_id', [42, 51, 60])
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->count();
        $yestoday_user_active_count = Db::name('api_subscribe')
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->where('status', 1)
            ->whereIn('product_id', [42, 51, 60])
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->count();
        $total_user_active_count = Db::name('api_subscribe')
            // ->where('status',1)
            ->whereIn('product_id', [42, 51, 60])
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->count() - 749;
        $total_user_active_count = $total_user_active_count > 0 ? $total_user_active_count : 0;

        $item_user_signin_count = Db::name('api_yuebao_log')
            ->where('income_time', '>=', strtotime($item_start_time))
            ->where('income_time', '<=', strtotime($item_end_time))
            ->where('type', 2)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->count();
        $yestoday_user_signin_count = Db::name('api_yuebao_log')
            ->where('income_time', '>=', strtotime($yesterday_start_time))
            ->where('income_time', '<=', strtotime($yesterday_end_time))
            ->where('type', 2)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->count();
        $total_user_signin_count = Db::name('api_yuebao_log')
            ->where('type', 2)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->count();
        $all_price_count = Db::name('api_fund_detail')
            ->where('data_type', 1)
            ->where('status', 1)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->sum('price');
        //昨天
        $yestoday_recharge_count = Db::name('api_fund_detail')
            ->where('data_type', 2)
            ->where('status', 1)
            ->where('order_no', '<>', null)
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //今天
        $today_recharge_count = Db::name('api_fund_detail')
            ->where('data_type', 2)
            ->where('status', 1)
            ->where('order_no', '<>', null)
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //所有
        $all_recharge_count = Db::name('api_fund_detail')
            ->where('data_type', 2)
            ->where('order_no', '<>', null)
            ->where('status', 1)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->sum('price');

        //昨天
        $yestoday_canwithdraw_count = Db::name('api_fund_detail')
            ->where('data_type', 21)
            ->where('status', 1)
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->where('create_time', '>=', '2025-01-06 00:00:00')
            ->where("remarks", "notlike", "%-%")
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //今天
        $today_canwithdraw_count = Db::name('api_fund_detail')
            ->where('data_type', 21)
            ->where('status', 1)
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->where('create_time', '>=', '2025-01-06')
            ->where("remarks", "notlike", "%-%")
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //所有
        $all_canwithdraw_count = Db::name('api_fund_detail')
            ->where('data_type', 21)
            ->where('create_time', '>=', '2025-01-06')
            ->where("remarks", "notlike", "%-%")
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->where('status', 1)
            ->sum('price');

        //今天
        $today_apply_count = Db::name('api_user_pension')
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->where('status', 1)
            ->where('pay_status', 1)
            ->group('user_id')
            ->count();
        $yestoday_apply_count = Db::name('api_user_pension')
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->when(true, function ($query) use ($admin) {
                $query->whereIn('user_id', $admin['subs']);
            })
            ->where('status', 1)
            ->where('pay_status', 1)
            ->group('user_id')
            ->count();
        //所有
        $all_apply_count = Db::name('api_user_pension')
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->where('status', 1)
            ->where('pay_status', 1)
            ->group('user_id')
            ->count();


        $today_account_count = Db::name('api_user_pension')
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })
            ->where('status', 1)
            ->where('pay_status', 1)
            ->group('user_id')
            ->count();

        $today_account_count = Db::name('api_subscribe')
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->where('sum_type', 3)
            ->when(true, function ($query) use ($admin) {
                if ($admin['id'] !== 1) {
                    $query->whereIn('user_id', $admin['subs']);
                }
            })->count();

        $yestoday_account_count = Db::name('api_subscribe')
        ->where('create_time', '>=', $yesterday_start_time)
        ->where('create_time', '<=', $yesterday_end_time)
        ->where('sum_type', 3)
        ->when(true, function ($query) use ($admin){
            if ($admin['id'] !== 1){
                $query->whereIn('user_id', $admin['subs']);
            }
        })->count();

        $all_account_count = Db::name('api_subscribe')
        ->where('sum_type', 3)
        ->when(true, function ($query) use ($admin){
            if ($admin['id'] !== 1){
                $query->whereIn('user_id', $admin['subs']);
            }
        })->count();


        $update = [
            'yesterday_user_count'       => $yesterday_user_count,
            'item_user_count'            => $item_user_count,
            'all_user_count'             => $all_user_count,
            'yesterday_manual_count'     => $yesterday_manual_count,
            'item_manual_count'          => $item_manual_count,
            'all_manual_count'           => $all_manual_count,
            'yesterday_price_count'      => $yesterday_price_count,
            'item_price_count'           => $item_price_count,
            'all_price_count'            => $all_price_count,
            'yesterday_investment_count' => $yesterday_investment_count,
            'item_investment_count'      => $item_investment_count,
            'all_investment_count'       => $all_investment_count,
            'yesterday_recharge_count'   => $yesterday_recharge_count,
            'item_recharge_count'        => $item_recharge_count,
            'all_recharge_count'         => $all_recharge_count,
            'user_total_price'           => $user_total_price,
            'user_status_count'           => $item_user_status_count,
            'yestoday_status_count'           => $yestoday_user_status_count,
            'total_status_count'           => $total_user_status_count,
            'user_active_count'           => $item_user_active_count,
            'yestoday_active_count'           => $yestoday_user_active_count,
            'total_active_count'           => $total_user_active_count,
            'user_signin_count'           => $item_user_signin_count,
            'yestoday_signin_count'           => $yestoday_user_signin_count,
            'total_signin_count'           => $total_user_signin_count,
            'all_price_count'           => $all_price_count,
            'user_open_wallet' => $user_open_wallet,
            'user_yestoday_open_wallet' => $user_yestoday_open_wallet,
            'user_open_wallet_total' => $user_open_wallet_total,
            'yestoday_recharge_count' => $yestoday_recharge_count,
            'today_recharge_count' => $today_recharge_count,
            'all_recharge_count' => $all_recharge_count,
            'yestoday_canwithdraw_count' => $yestoday_canwithdraw_count,
            'today_canwithdraw_count' => $today_canwithdraw_count,
            'all_canwithdraw_count' => $all_canwithdraw_count,
            'today_apply_count' => $today_apply_count,
            'yestoday_apply_count' => $yestoday_apply_count,
            'all_apply_count' => $all_apply_count,
            'today_account_count' => $today_account_count,
            'yestoday_account_count' => $yestoday_account_count,
            'all_account_count' => $all_account_count,
        ];
        Db::name('admin_admin_total')->where('admin_bind_id', $admin['admin_bind_id'])->update($update);
    }
}
