<?php
declare (strict_types=1);

namespace app\admin\controller\api;

use think\facade\Db;
use think\facade\Request;
// use app\common\service\ApiAccount as S;
// use app\common\model\ApiAccount as M;

class UserBenefits extends \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    public function index()
    {
        if (Request::isAjax()) {
            $param = $this->request->param();
            $limit = $param['limit'];
            $list = Db::name('api_voucher')->order('id','desc')->paginate($limit)->each(function ($item) {
                if($item['product_id'] != 0){
                    // $item['name'] = Db::name('api_product')->where('id', $item['product_id'])->value('name');
                } else {
                    // $item['name'] = '任意产品';
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

    public function give(){
        if (Request::isAjax()) {
            $data = $this->request->param();
            $mobiles_array = [];
            if ($data['mobiles']){
                $mobiles = explode("\n", $data['mobiles']);
                foreach ($mobiles as $v) {
                    if ($v) {
                        $mobiles_array[] = trim($v);
                    }
                }
            }
            if(!$data['id']){
                return $this->getJson(['msg' => '参数错误！', 'code' => 201]); 
            }
            $model = Db::name('api_voucher')->where('id',$data['id'])->find();
            if(strtotime($model['endtime']) <time()){
                return $this->getJson(['msg' => '当前券已过期,请更改结束时间!', 'code' => 201]);
            }
            if (!count($mobiles_array)) {
                return $this->getJson(['msg' => '请输入手机号，一行一个', 'code' => 201]);
            }
            Db::startTrans();
            try {
                $ids = Db::name('api_user')->whereIn('mobile', $mobiles_array)->column('id');
                if ($ids) {
                    foreach($ids as $id){
                        $price = $model['amount'];
                        $insert_data = [
                            'user_id'=>$id,
                            'voucher_id'=>$model['id'],
                            'amount'=>$price,
                            'addtime'=>strtotime($model['starttime']),
                            'invalidtime'=>strtotime($model['endtime']),
                            'type'=> strpos($model['name'],'资格券') !== false ?  1 : 0,
                        ];
                        $state = Db::name('api_benefits')->insert($insert_data);
                        $text = strpos($model['name'],'资格券') !== false  ? '资格券' : '代金券';
                        if($state){
                            Db::name('api_fund_detail')
                                ->insert([
                                    'data_type' => 15,
                                    'recharge_type' => 0,
                                    'user_id' => $id,
                                    'price' => $price,
                                    'node' => $data['node'],
                                    'status' => 1,
                                    'remarks' => "发放{$text}:".$price,
                                    'create_time' => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                    Db::commit();
                    return json(['msg' => '操作成功', 'code' => 200]);
                }
                Db::rollback();
                return $this->getJson(['msg' => '操作失败', 'code' => 201]);
            } catch (\Exception $e) {
                Db::rollback();
                return $this->getJson(['msg' => '操作失败', 'code' => 201]);
            }
        }
        $model = Db::name('api_voucher')->where('id', $this->request->param('id'))->find();
        $this->assign('model', $model);
        return $this->fetch();
    }
    
    public function add(){
        if (Request::isAjax()) {
            $param = $this->request->param();
            if(!$param['amount']){
                return ['code'=>201,'msg'=>'请输入代金券金额'];
            }
            if(!$param['starttime']){
                return ['code'=>201,'msg'=>'请输入开始时间'];
            }
            if(!$param['endtime']){
                return ['code'=>201,'msg'=>'请输入结束时间'];
            }
            if(strtotime($param['starttime']) >strtotime($param['endtime'])){
                return ['code'=>201,'msg'=>'开始时间不能大于结束时间'];
            }
            $ret = Db::name('api_voucher')->insert($param);
            if($ret){
                return ['code'=>200,'msg'=>'添加成功'];
            } else {
                return ['code'=>201,'msg'=>'添加失败'];
            }
        }
        // $products = Db::name('api_product')->select();
        // $this->assign('products', $products);
        return $this->fetch();
    }
    
    public function edit(){
        if (Request::isAjax()) {
            $param = $this->request->param();
            $id = $param['id'];
            unset($param['id']);
            $param['addtime'] = time();
            if(strtotime($param['starttime']) >strtotime($param['endtime'])){
                return ['code'=>201,'msg'=>'开始时间不能大于结束时间'];
            }
            $ret = Db::name('api_voucher')->where('id', $id)->update($param);
            if($ret){
                return ['code'=>200,'msg'=>'编辑成功'];
            } else {
                return ['code'=>201,'msg'=>'编辑失败'];
            }
        }
        $list = Db::name('api_voucher')->where('id', $this->request->param('id'))->find();
        // $products = Db::name('api_product')->select();
        // $this->assign('products', $products);
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
