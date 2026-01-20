<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;
class ApiCheckTime extends Model
{
    use SoftDelete;
     protected $deleteTime = false;
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        
        $list = self::order('id','desc')->where('stype', 1)->where($where)->paginate($limit)->each(function ($item){
            $user = \think\facade\Db::name('api_user')
                ->field('*')
                ->where('id',$item->user_id)
                ->find();
            $item->mobile = $user['mobile'];

            return $item;
        });
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}
