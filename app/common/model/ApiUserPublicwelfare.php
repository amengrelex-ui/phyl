<?php
declare (strict_types = 1);

namespace app\common\model;

use think\facade\Db;
use think\Model;
use think\model\concern\SoftDelete;
class ApiUserPublicwelfare extends Model
{
    use SoftDelete;
     protected $deleteTime = "delete_time";
    // 获取公益列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        
       //按产品名称查找
       if ($name = input("name")) {
           $where[] = ["name", "like", "%" . $name . "%"];
       }
        $list = self::order('id','desc')
            ->where($where)
            ->paginate($limit)
            ->each(
                function ($item) {
                    // if($item['method'] == 1){
                    //     $item['method'] = '天';
                    // } else if($item['method'] == 2){
                    //     $item['method'] ='小时';
                    // } else{
                    //     $item['method'] ='分钟';
                    // }

                    $item->proceeds = $item->proceeds;
                    return $item;
                }
            );
        // print_r($list->items());exit();
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
    
}
