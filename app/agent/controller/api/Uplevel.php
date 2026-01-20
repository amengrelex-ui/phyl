<?php
declare (strict_types = 1);

namespace app\agent\controller\api;

use think\facade\Request;
use think\facade\Db;

class Uplevel extends  \app\agent\controller\Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];

    // 列表
    public function index(){
        if (Request::isAjax()) {
            $param = $this->request->param();
            $limit = $param['limit'];
            $list = [];
            $list = Db::name('api_user')
              ->where(function ($query) {
                $query->where('invitees', '>=', 500)
                    ->where('level', 4);
            })->whereOr(function ($query) {
                $query->where('invitees', '>=', 1000)
                    ->where('level', 3);
            })->whereOr(function ($query) {
                $query->where('invitees', '>=', 5000)
                    ->where('level', 2);
            })->paginate($limit);
            
            if(!empty($list)){
                return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
            }else {
                return ['code'=>0,'data'=>$list];
            }
        }
        return $this->fetch();
    }
    
    
    //升级
    public function distribute(){
        if(Request::isAjax()){
            $param = $this->request->param();
            $user = Db::name('api_user')->where('id',$param['id'])->find();
            if (!$user) {
                return returnJson(301, '未找到当前用户请重新登录');
            }
            $ret = false;
            if($user['invitees'] >= 500 && $user['level'] == 4){
                $ret = Db::name('api_user')->where('id', $user['id'])->update([
                    'level' => 3
                ]);
            } else if($user['invitees'] >= 1000 && $user['level'] == 3){
                $ret = Db::name('api_user')->where('id', $user['id'])->update([
                    'level' => 2
                ]);
            } else if($user['invitees'] >= 5000 && $user['level'] == 2){
                $ret = Db::name('api_user')->where('id', $user['id'])->update([
                    'level' => 1
                ]);
            }
            if($ret){
                return returnJson(200, '升级成功！');
            } else {
                return returnJson(200, '非法操作！');
            }
        }
    }
}
