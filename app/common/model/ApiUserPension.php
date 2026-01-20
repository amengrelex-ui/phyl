<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;

class ApiUserPension extends Model
{
    use SoftDelete;
    protected $deleteTime = false;
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        //按用户id查找
        if ($mobile = input("mobile")) {
            $where[] = ["au.mobile", "=", $mobile];
        }
        $list = self::alias('ap')
        ->leftJoin('api_user au','au.id=ap.user_id')
        ->where($where)->field('au.mobile,ap.*')->order('ap.status','desc')->order('ap.id','desc')->paginate($limit);
        return ['code' => 0, 'data' => $list->items(), 'extend' => ['count' => $list->total(), 'limit' => $limit]];
    }
}
