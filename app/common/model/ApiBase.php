<?php
declare (strict_types = 1);

namespace app\common\model;

use think\facade\Db;
use think\Model;
use think\facade\Request;
use think\facade\Session;
use think\model\concern\SoftDelete;
class ApiBase extends Model
{
    // 定义全局的查询范围
    protected $globalScope = ['id'];

    public function scopeId($query)
    {
        if($admins = Session::get('admin') ){
            $admin = AdminAdmin::find($admins['id']);
            if($admin['admin_bind_id'] && $admin['id']>1){
                $query->whereIn('user_id',$admin['subs']);
            }
        }
    }
}
