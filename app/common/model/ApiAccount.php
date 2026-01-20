<?php
declare (strict_types = 1);

namespace app\common\model;

use think\facade\Db;
use think\Model;
use think\model\concern\SoftDelete;
class ApiAccount extends Model
{
    use SoftDelete;
     protected $deleteTime = "delete_time";
    // 获取列表
    public static function getList($id=0)
    {
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.mobile')) {
            $where[] = ['u.mobile', 'like', "%".$search."%"];
        }
        
        if($username = input('get.account_name')){
            $where[] = ['a.account_name', 'like', $username];
        }
        
        if( $range =  input('get.range')){
            $dates = explode('~', $range);
            $where[] = ['a.create_time','>', trim($dates[0])." 00:00:00"];
            $where[] = ['a.create_time','<', trim($dates[1])." 23:59:59"];
        }
        $where[] = ['a.delete_time','=',null];

        $config = config('web');
        $list = Db::name('api_account')->alias('a')->field('a.*,u.mobile,u.username')->join('api_user u', 'a.user_id=u.id')->order('id','desc')->where($where)->paginate($limit);
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit,'card' => isset($card) ? $card : []]];
    }
    
    
    
    
   
    
    
    
}
