<?php
declare (strict_types=1);

namespace app\agent\controller\api;

use think\facade\Db;
use think\facade\Request;
// use app\common\service\ApiAccount as S;
// use app\common\model\ApiAccount as M;

class UserBenefits extends \app\agent\controller\Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    public function index()
    {
        if (Request::isAjax()) {
            $param = $this->request->param();
            $limit = $param['limit'];
            $list = Db::name('api_voucher')->paginate($limit)->each(function ($item) {
                if($item['product_id'] != 0){
                    $item['name'] = Db::name('api_product')->where('id', $item['product_id'])->value('name');
                } else {
                    $item['name'] = '任意产品';
                }
                if($item['method'] == 1){
                    $task = "邀请".$item['num']."人注册即可领取";
                } else{
                    $task = "下级有".$item['num']."人完成激活即可领取";
                }
                $item['task'] = $task;
                return $item;
            });
            return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
        }

        return $this->fetch();
    }
    
    public function add(){
        if (Request::isAjax()) {
            $param = $this->request->param();
            $ret = Db::name('api_voucher')->insert($param);
            if($ret){
                return ['code'=>200,'msg'=>'添加成功'];
            } else {
                return ['code'=>201,'msg'=>'添加失败'];
            }
        }
        $products = Db::name('api_product')->select();
        $this->assign('products', $products);
        return $this->fetch();
    }
    
    public function edit(){
        if (Request::isAjax()) {
            $param = $this->request->param();
            $id = $param['id'];
            unset($param['id']);
            $param['addtime'] = time();
            $ret = Db::name('api_voucher')->where('id', $id)->update($param);
            if($ret){
                return ['code'=>200,'msg'=>'编辑成功'];
            } else {
                return ['code'=>201,'msg'=>'编辑失败'];
            }
        }
        $list = Db::name('api_voucher')->where('id', $this->request->param('id'))->find();
        $products = Db::name('api_product')->select();
        $this->assign('products', $products);
        $this->assign('list', $list);
        return $this->fetch();
    }
    
        // 删除
    public function remove(){
        
        if (Request::isAjax()) {
            $param = $this->request->param();
            $api_voucher = Db::name('api_voucher');
            $model = $api_voucher->where('id',$param['id'])->find();
            if(empty($model)){
                return ['msg'=>'数据不存在','code'=>201];
            }
            try{
                $api_voucher->where('id', $param['id'])->delete();
                return ['msg'=>'删除成功','code'=>200];
            }catch (\Exception $e){
                return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
            }
        }
        
    }

    // 批量删除
    public function batchRemove(){
        return $this->getJson(S::goBatchRemove(Request::post('ids')));
        }
    }
