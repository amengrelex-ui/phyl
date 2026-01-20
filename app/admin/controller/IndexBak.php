<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\common\model\AdminAdmin;
use think\facade\Session;
use think\facade\Request;
use app\common\util\Upload as Up;
use app\common\model\AdminPhoto as P;
use app\common\service\AdminAdmin as S;
use think\facade\Db;

class IndexBak extends Base
{
    protected $middleware = ['AdminCheck'];

    // 首页
    public function index()
    {
        return $this->fetch('', [
            'nickname'  => get_field('admin_admin', Session::get('admin.id'), 'nickname')
        ]);
    }

    // 清除缓存
    public function cache()
    {
        Session::clear();
        return $this->getJson(rm());
    }

    // 菜单
    public function menu()
    {
        return json(get_tree(Session::get('admin.menu')));
    }

    // 欢迎页
    public function home()
    {
        $time = time();
        // 昨天时间
        $yesterday_start_time = date('Y-m-d ', $time - 86400) . '00:00:00';
        $yesterday_end_time = date('Y-m-d ', $time - 86400) . '23:59:59';
        // 今天时间
        $item_start_time = date('Y-m-d ') . '00:00:00';
        $item_end_time = date('Y-m-d ') . '23:59:59';
        $admin = AdminAdmin::where('id',Session::get('admin.id'))->find();
        $item_user_status_count = Db::name('api_user')
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->where('status', 1)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('id',$admin['subs']);
                }
            })
            ->count();
        $yestoday_user_status_count = Db::name('api_user')
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->where('status', 1)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('id',$admin['subs']);
                }
            })
            ->count();

        $user_open_wallet = Db::name('api_user')
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->where('huimin_apply', 2)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('id',$admin['subs']);
                }
            })
            ->count();
        $user_yestoday_open_wallet = Db::name('api_user')
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->where('huimin_apply', 2)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('id',$admin['subs']);
                }
            })
            ->count();
        $user_open_wallet_total = Db::name('api_user')
            ->where('huimin_apply', 2)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('id',$admin['subs']);
                }
            })
            ->count();

        $total_user_status_count = Db::name('api_user')
            ->where('status', 1)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('id',$admin['subs']);
                }
            })
            ->count();

        $item_user_active_count = Db::name('api_subscribe')
            ->where('create_time', '>=', $item_start_time)
            ->where('create_time', '<=', $item_end_time)
            ->where('status', 1)
            ->whereIn('product_id', [42,51,60])
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->count();
        $yestoday_user_active_count = Db::name('api_subscribe')
            ->where('create_time', '>=', $yesterday_start_time)
            ->where('create_time', '<=', $yesterday_end_time)
            ->where('status', 1)
            ->whereIn('product_id', [42,51,60])
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->count();
        $total_user_active_count = Db::name('api_subscribe')
            // ->where('status',1)
            ->whereIn('product_id', [42,51,60])
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->count()-749;
        $total_user_active_count = $total_user_active_count>0 ? $total_user_active_count : 0;

        $item_user_signin_count = Db::name('api_yuebao_log')
            ->where('income_time', '>=', strtotime($item_start_time))
            ->where('income_time', '<=', strtotime($item_end_time))
            ->where('type', 2)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->count();
        $yestoday_user_signin_count = Db::name('api_yuebao_log')
            ->where('income_time', '>=', strtotime($yesterday_start_time))
            ->where('income_time', '<=', strtotime($yesterday_end_time))
            ->where('type', 2)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->count();
        $total_user_signin_count = Db::name('api_yuebao_log')
            ->where('type', 2)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->count();
        $all_price_count = Db::name('api_fund_detail')
            ->where('data_type', 1)
            ->where('status', 1)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->sum('price');
        //昨天
        $yestoday_recharge_count = Db::name('api_fund_detail')
            ->where('data_type',2)
            ->where('status',1)
            ->where('order_no','<>',null)
            ->where('create_time','>=',$yesterday_start_time)
            ->where('create_time','<=',$yesterday_end_time)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //今天
        $today_recharge_count = Db::name('api_fund_detail')
            ->where('data_type',2)
            ->where('status',1)
            ->where('order_no','<>',null)
            ->where('create_time','>=',$item_start_time)
            ->where('create_time','<=',$item_end_time)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //所有
        $all_recharge_count = Db::name('api_fund_detail')
            ->where('data_type',2)
            ->where('order_no','<>',null)
            ->where('status',1)
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->sum('price');

        //昨天
        $yestoday_canwithdraw_count = Db::name('api_fund_detail')
            ->where('data_type',21)
            ->where('status',1)
            ->where('create_time','>=',$yesterday_start_time)
            ->where('create_time','<=',$yesterday_end_time)
            ->where('create_time','>=','2025-01-06 00:00:00')
            ->where("remarks","notlike","%-%")
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //今天
        $today_canwithdraw_count = Db::name('api_fund_detail')
            ->where('data_type',21)
            ->where('status',1)
            ->where('create_time','>=',$item_start_time)
            ->where('create_time','<=',$item_end_time)
            ->where('create_time','>=','2025-01-06')
            ->where("remarks","notlike","%-%")
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->group('user_id')
            ->sum('price');
        //所有
        $all_canwithdraw_count = Db::name('api_fund_detail')
            ->where('data_type',21)
            ->where('create_time','>=','2025-01-06')
            ->where("remarks","notlike","%-%")
            ->when(true,function ($query) use($admin){
                if($admin['id']!==1){                
                    $query->whereIn('user_id',$admin['subs']);
                }
            })
            ->where('status',1)
            ->sum('price');

        $new = [
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
        ];
        return $this->fetch('', array_merge($this->getSystem(), $new));
    }

    // 修改密码
    public function pass()
    {
        if (Request::isAjax()) {
            $this->getJson(S::goPass());
        }
        return $this->fetch();
    }

    // 通用上传
    public function upload()
    {
        return $this->getJson(Up::putFile(Request::file(), Request::post('path')));
    }

    // 图库选择
    public function optPhoto()
    {
        if (Request::isAjax()) {
            return $this->getJson(P::getAll());
        }
        return $this->fetch('', P::getPath());
    }
}
