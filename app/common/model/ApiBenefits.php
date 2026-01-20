<?php
declare (strict_types = 1);

namespace app\common\model;

use think\facade\Db;
use think\Model;
use think\model\concern\SoftDelete;
class ApiBenefits extends Model
{
    use SoftDelete;
     protected $deleteTime = "delete_time";
    // 获取列表
    public static function getList($id=0)
    {
        $where = [];
        $limit = input('get.limit');
        // if ($search = input('get.mobile')) {
        //     $where[] = ['mobile', 'like', "%".$search."%"];
        // }
        
        // if($ip = input('get.ip')){
        //     $where[] = ['login_ip', '=', $ip];
        // }
        
        // if($username = input('get.username')){
        //     $where[] = ['username', 'like', $username];
        // }
        
        // if(input('get.status') >-1){
        //     $where[] = ['status', '=', input('get.status')];
        // }

        // $card = [];
        // if ($id){
        //     $total_one = Db::name('api_user')
        //         ->where('parent_user_id',$id)
        //         ->column('id');

        //     $total_tow = [];
        //     if (!empty($total_one)){
        //         $total_tow = Db::name('api_user')
        //             ->where('parent_user_id', 'in', $total_one)
        //             ->column('id');
        //     }

        //     $user_id = array_merge($total_one, $total_tow);

        //     $card = Db::name('api_account')
        //         ->where('user_id',$id)
        //         ->where('delete_time IS NULL')
        //         ->find();
        //     if (!$user_id){
        //         return ['code'=>0,'data'=>[],'extend'=>['count' => 0, 'limit' => $limit,'card' => isset($card) ? $card : []]];
        //     }
        //     $where[] = ['id','in',$user_id];
        // }

        $config = config('web');
        $list = self::order('id','desc')->where($where)->paginate($limit)->each(function ($item)use($config,$id){
            if ($item){
                $user = \think\facade\Db::name('api_account')
                    ->field('*')
                    ->find();
                
            }
            
            
        });
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit,'card' => isset($card) ? $card : []]];
    }
    
    
    
    
   
    
    
    
}
