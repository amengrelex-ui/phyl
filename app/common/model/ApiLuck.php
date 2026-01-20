<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Db;
use think\Model;
use think\model\concern\SoftDelete;
class ApiLuck extends Model
{
    use SoftDelete;
     protected $deleteTime = "delete_time";
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        
        $list = self::order('id','desc')->where($where)->paginate($limit)->each(function ($item){
            if ($item['status'] == 1){
                $item->status = '中奖';
            }else{
                $item->status = '未中奖';
            }

            if ($item['award_type'] == 1){
                $item->award_type = '金额';
            }else{
                $item->award_type = '实物';
            }

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
