<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;
class ApiJournalism extends Model
{
    use SoftDelete;
     protected $deleteTime = "delete_time";
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        
               //按标题查找
               if ($title = input("title")) {
                   $where[] = ["title", "like", "%" . $title . "%"];
               }
        $list = self::order('id','desc')->where($where)->paginate($limit);
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}
