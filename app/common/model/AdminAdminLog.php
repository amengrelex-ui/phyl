<?php

declare(strict_types=1);

namespace app\common\model;

use think\facade\Db;
use think\facade\Request;
use think\facade\Session;
use think\Model;

class AdminAdminLog extends Model
{

    public function log()
    {
        return $this->belongsTo('AdminAdmin', 'uid', 'id');
    }

    // 管理员日志记录
    public static function record()
    {
        $desc = Request::except(['s', '_pjax']) ?? '';
        if (isset($desc['page']) && isset($desc['limit'])) return;
        $url = "/" . Request::controller(true) . '/' . Request::action();
        if ($url !== "/login/index") {
            if (in_array(Request::action(), ['index', 'log', 'exchange', 'applyWallet', 'sublist', 'home', 'list', 'integralaudio'])) return;
        }
        if (Request::method() != 'POST') return;
        $controller = Request::controller(true);
        $action = Request::action();
        foreach ($desc as $k => $v) {
            if (stripos($k, 'fresh') !== false) return;
            if (is_string($v) && strlen($v) > 255 || stripos($k, 'password') !== false) {
                unset($desc[$k]);
            }
        }
        $operate = self::getOperateName($url, $controller, $action);
        if (!$operate) {
            file_put_contents('url.txt', "=================start===================" . PHP_EOL, FILE_APPEND);
            file_put_contents('url.txt', $controller . PHP_EOL, FILE_APPEND);
            file_put_contents('url.txt', $action . PHP_EOL, FILE_APPEND);
            file_put_contents('url.txt', $url . PHP_EOL, FILE_APPEND);
            return;
        }
        $info = [
            'operate' => $operate,
            'uid'       => Session::get('admin.id'),
            'url'      => $url,
            'desc'    => json_encode($desc),
            'ip'       => Request::ip(),
            'user_agent' => Request::server('HTTP_USER_AGENT')
        ];
        $res = self::where('uid', $info['uid'])
            ->order('id', 'desc')
            ->find();
        if (isset($res['url']) !== $info['url']) {
            self::create($info);
        }
    }

    public static function getOperateName($url, $controller = '', $action = '')
    {
        if ($controller == 'api.userbenefits') {
            $name = '代金券管理';
            switch ($action) {
                case 'add':
                    return $name .= '->添加代金券';
                    break;
                case 'edit':
                    return $name .= '->编辑代金券';
                    break;
                case 'remove':
                    return $name .= '->删除代金券';
                    break;
                case 'give':
                    return $name .= '->发放代金券';
                    break;
            }
        } elseif ($controller == 'api.userwithdraw') {
            $name = '用户提现';
            switch ($action) {
                case 'data_success':
                    return $name .= '->确认提现';
                    break;
                case 'refuse':
                    return $name .= '->拒绝提现';
                    break;
                case 'xiafa_success':
                    return $name .= '->提现下发';
                    break;
                case 'batchPassed':
                    return $name .= '->批量通过';
                    break;
                case 'batchRefuse':
                    return $name .= '->批量拒绝';
                    break;
            }
        } elseif ($controller == 'api.user') {
            $name = '用户管理';
            switch ($action) {
                case 'user_recharge':
                    return $name .= '->资金操作';
                    break;
                case 'set_subagent':
                    return $name .= '->设为子代理';
                    break;
                case 'set_agent':
                    return $name .= '->设为代理';
                    break;
                case 'set_apply_status':
                    return '审核开通钱包->开通钱包';
                    break;
                case 'allfunds':
                    return $name .= '->批量发放';
                    break;
                case 'set_agent':
                    return $name .= '->设为代理';
                    break;
                case 'set_agent':
                    return $name .= '->设为代理';
                    break;
            }
        } else {
            switch ($url) {
                case '/login/index':
                    return '管理员登录';
                    break;
                case '/api.userrecharge/data_success':
                    return '用户充值->确认充值';
                    break;
                case '/api.product/status':
                    return '产品管理->改变状态';
                    break;
                default:
                    $per = Db::name('admin_permission')->where('href', $url)->field('pid,title')->find();
                    $name = '';
                    if ($per) {
                        if ($per['pid']) {
                            $per2 = Db::name('admin_permission')->where('id', $per['pid'])->field('title')->find();
                            if ($per2) {
                                $name .= $per2['title'];
                            }
                        }
                        $name .= "->" . $per['title'];
                    }
                    return $name;
                    break;
            }
        }
    }
}
