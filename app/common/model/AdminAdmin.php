<?php
declare (strict_types = 1);

namespace app\common\model;

use think\facade\Db;
use think\Model;
use think\model\concern\SoftDelete;
use think\facade\Session;
class AdminAdmin extends Model
{
    use SoftDelete;

    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.username')) {
            $where[] = ['username', 'like', "%" . $search . "%"];
        }
        $list = self::order('id','desc')->where('id','<>',Session::get('admin.id'))->where('id','>','1')->where('status', 1)->withoutField('password,token,delete_time')->where($where)->paginate($limit)->each(function ($item){
            $item['user_name'] = $item->admin_bind_id ? Db::name('api_user')->where('id',$item->admin_bind_id)->value('mobile') : "";
            return $item;
        });
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }

    // 获取日志列表
    public static function getLog()
    {
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.uid')) {
            $where[] =  ['uid', '=',$search];
        }
        $list = AdminAdminLog::with('log')->order('id','desc')->where($where)->paginate($limit)->each(function ($item){
            return $item;
        });
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }

    // 获取日志列表
    public static function getLog2()
    {
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.uid')) {
            $where[] =  ['uid', '=',$search];
        }
        $list = AdminAdminLog::with('log')->order('id','desc')->where($where)->paginate($limit);
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }

    // 管理拥有的角色
    public function roles()
    {
        return $this->belongsToMany('AdminRole', 'admin_admin_role', 'role_id', 'admin_id');
    }

    // 获取管理拥有的角色
    public static function getRole($id)
    {
        $admin = self::with('roles')->where('id',$id)->find();
        $roles = AdminRole::select();
        foreach ($roles as $k=>$role){
            if (isset($admin->roles) && !$admin->roles->isEmpty()){
                foreach ($admin->roles as $v){
                    if ($role['id']==$v['id']){
                        $roles[$k]['own'] = true;
                    }
                }
            }
        }
        return ['admin'=>$admin,'roles'=>$roles];
    }

    // 获取用户直接权限
    public static function getPermission($id)
    {
        $admin = self::with('directPermissions')->find($id);
        $permissions = AdminPermission::order('sort','asc')->select();
        foreach ($permissions as $permission){
            foreach ($admin->direct_permissions as $v){
                if ($permission->id == $v['id']){
                    $permission->own = true;
                }
            }
        }
        $permissions = get_tree($permissions->toArray());
        return ['admin'=>$admin,'permissions'=>$permissions];
    }


    // 管理的直接权限
    public function directPermissions()
    {
        return $this->belongsToMany('AdminPermission', 'admin_admin_permission', 'permission_id', 'admin_id');
    }

    public function totals()
    {
        return $this->hasOne('AdminAdminTotal','admin_bind_id','admin_bind_id');
    }

    public function getSubsAttr($value)
    {
        return ($value) ? explode(",",$value) : [];
    }

    // 用户的所有权限
    public static function permissions($id,$root)
    {
        $admin = self::with(['roles.permissions', 'directPermissions'])->findOrEmpty($id)->toArray();
        $permissions = [];
        //超级管理员缓存所有权限
        if ($admin['stype'] == 1){
            $perms = AdminPermission::order('sort','asc')->where('stype', 1)->select()->toArray();
            foreach ($perms as $p){
                if($p['status'] == 1){
                    $permissions[$p['id']] =  $p;
                    $permissions[$p['id']]['href'] = is_url($p['href'])??$root.$p['href'];
                 }
            }
            if(env('APP_DEBUG')==true){
                $permissions[0] = [
                    "id" => -1,
                    "pid" => 0,
                    "title" => "自动生成",
                    "icon" => "layui-icon layui-icon-util",
                    "type" => 0,
		            "href" => "",
                ];
                $permissions[-1] = [
                    "id" => -2,
                    "pid" => -1,
                    "title" => "CRUD管理",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    'href'=> $root."/crud/index",
                ];
            }
        }else{
            $perms = AdminPermission::order('sort','asc')->where('stype',1)->select()->toArray();
            $rolePermissions = Db::name('admin_role_permission')->where('role_id',2)->column('permission_id');
            foreach ($perms as $p){
                if($p['status'] == 1){
                    if(in_array($p['id'],$rolePermissions)){
                        $permissions[$p['id']] =  $p;
                        $permissions[$p['id']]['href'] = is_url($p['href'])??$root.$p['href'];
                    }
                 }
            }
            if(env('APP_DEBUG')==true){
                $permissions[0] = [
                    "id" => -1,
                    "pid" => 0,
                    "title" => "自动生成",
                    "icon" => "layui-icon layui-icon-util",
                    "type" => 0,
		            "href" => "",
                ];
                $permissions[-1] = [
                    "id" => -2,
                    "pid" => -1,
                    "title" => "CRUD管理",
                    "icon" => "layui-icon layui-icon-console",
                    "type" => 1,
                    "openType" => "_iframe",
                    'href'=> $root."/crud/index",
                ];
            }
        }
        //合并权限为用户的最终权限
        return $permissions;
    }

}
