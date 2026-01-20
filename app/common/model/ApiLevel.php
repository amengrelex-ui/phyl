<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;
class ApiLevel extends Model
{
    use SoftDelete;
     protected $deleteTime = false;
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        
               //按档位查找
               if ($name = input("name")) {
                   $where[] = ["name", "like", "%" . $name . "%"];
               }
        $list = self::order('id','asc')->where($where)->paginate($limit);
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}
