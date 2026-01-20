<?php

declare(strict_types=1);

namespace app\common\model;

use think\facade\Db;
use think\facade\Session;
use think\Model;
use think\model\concern\SoftDelete;

class ApiSubscribe extends ApiBase
{
    protected $deleteTime = "delete_time";

    // 获取列表
    public static function getList($data = [])
    {
        $where = [];
        $limit = input('get.limit');
        if ($search = input('get.mobile')) {
            $user_id = Db::name('api_user')
                ->where('mobile', 'like', "%" . $search . "%")
                ->column('id');
            if (empty($user_id)) {
                return ['code' => 0, 'data' => [], 'extend' => ['count' => 0, 'limit' => $limit]];
            }
            $where[] = ['user_id', 'in', $user_id];
        }

        if ($search = input('get.product_type')) {
            $where[] = ['product_type', 'in', $search];
        }

        if ($search = input('get.status')) {
            if ($search != 3) {
                $where[] = ['status', 'in', $search];
            }
        }

        if ($search = input('get.name')) {
            $where[] = ['name', 'like', "%{$search}%"];
        }

        $list = self::order('id', 'desc')->where($where);
        if (input('get.status') == 3) {
            $list = $list->onlyTrashed();
        }
        $list = $list->paginate($limit)->each(function ($item) {
            $user = \think\facade\Db::name('api_user')
                ->field('*')
                ->where('id', $item->user_id)
                ->find();
            $item->mobile = $user['mobile'];

            switch ($item->product_type) {
                case 0:
                    $item->product_type = '兑换券';
                    break;
                case 1:
                    $item->product_type = '养老补助';
                    break;
                case 2:
                    $item->product_type = '消费补贴';
                    break;
                case 3:
                    $item->product_type = '居民保险';
                    break;
            }

            if ($item->delete_time) {
                // $item->status = '未支付';
            } else {
                switch ($item->status) {
                    case 1:
                        $item->status = '认购中';
                        break;
                    case 2:
                        $item->status = '已到期';
                        break;
                    case 2:
                        $item->status = '已返现';
                        break;
                }
            }

            return $item;
        });
        return ['code' => 0, 'data' => $list->items(), 'extend' => ['count' => $list->total(), 'limit' => $limit]];
    }

    public function getCouponTotal($user)
    {
        $coupon_total = Db::name('api_subscribe')
            ->alias('as')
            ->join('api_product ap', 'ap.id = as.product_id')
            ->where('as.update_time', '=', '0000-00-00 00:00:00')->where('as.user_id', $user['id'])->where('as.product_type', 0)->sum('ap.minimum_investment');
        return $coupon_total ?? 0;
    }

    public function getCouponList($user)
    {
        $list = Db::name('api_subscribe')
            ->alias('as')
            ->join('api_product ap', 'ap.id = as.product_id')
            ->field('as.id,as.update_time,ap.minimum_investment as coupon_price')
            ->where('as.update_time', '=', '0000-00-00 00:00:00')->where('as.user_id', $user['id'])->where('as.product_type', 0)->order('as.id')->select();
        return $list;
    }
}
