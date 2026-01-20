<?php

declare(strict_types=1);

namespace app;

use app\common\model\AdminAdmin;
use think\App;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Session;
use think\Validate;
use think\facade\View;

/**
 * 控制器基础类
 */
abstract class AdminBaseBakController
{
    use \app\common\traits\Base;
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize() {}

    // 获取系统参数
    protected function getSystem()
    {
        $time = time();
        // 昨天时间
        $yesterday_start_time = date('Y-m-d ', $time - 86400) . '00:00:00';
        $yesterday_end_time = date('Y-m-d ', $time - 86400) . '23:59:59';
        // 今天时间
        $item_start_time = date('Y-m-d ') . '00:00:00';
        $item_end_time = date('Y-m-d ') . '23:59:59';
        $admin = AdminAdmin::where('id', Session::get('admin.id'))->find();
        if ($admin['id' == 1]){
            //昨天
            $yesterday_user_count = Db::name('api_user')
                ->where('create_time', '>=', $yesterday_start_time)
                ->where('create_time', '<=', $yesterday_end_time)
                ->when(true, function ($query) use ($admin) {
                    if ($admin['id'] !== 1) {
                        $query->whereIn('id', $admin['subs']);
                    }
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
        }
        return [
            'os' => PHP_OS,
            'space' => round((disk_free_space('.') / (1024 * 1024)), 2) . 'M',
            'addr' => $_SERVER['HTTP_HOST'],
            'run' => $this->request->server('SERVER_SOFTWARE'),
            'php' => PHP_VERSION,
            'php_run' => php_sapi_name(),
            'mysql' => function_exists('mysql_get_server_info') ? mysql_get_server_info() : \think\facade\Db::query('SELECT VERSION() as mysql_version')[0]['mysql_version'],
            'think' => $this->app->version(),
            'upload' => ini_get('upload_max_filesize'),
            'max' => ini_get('max_execution_time') . '秒',
            'ver' => 'V5.0.1',
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
        ];
    }

    protected function getJson($json = [])
    {
        if ('json' == strtolower($this->getResponseType())) {
            return $this->json($json['msg'] ?? '操作成功', $json['code'] ?? 200, $json['data'] ?? [], $json['extend'] ?? []);
        }
    }

    //页面分配变量
    protected function assign($key, $value)
    {
        return View::assign($key, $value);
    }

    //页面渲染 
    protected function fetch($template = '', $data = [])
    {
        return View::fetch($template, $data);
    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }
}
