<?php

declare(strict_types=1);

namespace app\api\controller;

use think\facade\Db;
use think\Request;

/**
 * 调试用，用户后去掉
 */
class Test extends \app\BaseController
{
    // 不设置UserCheck中间件，所有方法免登录

    /**
     * 商户余额查询接口（免登录）
     * 根据商户号查询余额
     * 请求方式: POST
     * 参数: mchNo (商户号)
     */
    public function query_balance(Request $request)
    {
        $mchNo = $request->param('mchNo', '');
        
        if (empty($mchNo)) {
            return returnJson(400, '商户号不能为空');
        }

        // 查找支付配置
        $pay_info = Db::name('api_paylist')
            ->where('payname', 'laicai2pay')
            ->where('mchid', $mchNo)
            ->find();
        
        if (!$pay_info || empty($pay_info['appkey'])) {
            return returnJson(400, '未找到该商户号的配置信息');
        }

        // 构建请求参数
        $reqTime = round(microtime(true) * 1000); // 13位时间戳（毫秒）
        
        $request_data = [
            'mchNo' => $mchNo,
            'reqTime' => $reqTime,
        ];
        
        // 生成签名
        $request_data['sign'] = generate_signature($request_data, $pay_info['appkey']);
        
        // 调用第三方API查询余额
        $apiUrl = "https://laicai2-pay-api.yzzf66.com/api/mch/queryBalance";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json;charset=UTF-8']);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return returnJson(500, '请求失败：' . $curlError);
        }
        
        if (!$res) {
            return returnJson(500, '请求失败，未收到响应');
        }
        
        $jsonData = json_decode($res, true);
        
        if (!$jsonData) {
            return returnJson(500, '响应数据格式错误');
        }
        
        // 验证响应签名（可选）
        if (isset($jsonData['sign']) && isset($jsonData['data'])) {
            $response_sign = $jsonData['sign'];
            $sign_data = $jsonData;
            unset($sign_data['sign']);
            $calc_sign = generate_signature($sign_data, $pay_info['appkey']);
            if (strtoupper($calc_sign) != strtoupper($response_sign)) {
                // 签名验证失败，记录日志但不阻止返回
                $path = "./logs";
                if (!file_exists($path)) {
                    mkdir("$path", 0700);
                }
                $logFile = fopen("{$path}/balance_query_sign_error.txt", "a");
                fwrite($logFile, date('Y-m-d H:i:s') . " - 签名验证失败: 计算签名={$calc_sign}, 响应签名={$response_sign}\n");
                fclose($logFile);
            }
        }
        
        // 处理响应
        if (isset($jsonData['code']) && $jsonData['code'] == 0) {
            // 成功
            $data = isset($jsonData['data']) ? $jsonData['data'] : [];
            // 将余额从分转换为元
            if (isset($data['balance'])) {
                $data['balance_yuan'] = $data['balance'] / 100;
            }
            return returnJson(200, isset($jsonData['msg']) ? $jsonData['msg'] : '查询成功', $data);
        } else {
            // 失败
            $error_msg = isset($jsonData['msg']) ? $jsonData['msg'] : '查询失败';
            return returnJson(400, $error_msg, $jsonData);
        }
    }

    /**
     * 查询支付配置接口（免登录）
     * 返回api_paylist所有记录
     * 请求方式: GET/POST
     * 不需要传参
     */
    public function paylist_config(Request $request)
    {
        // 查询所有支付配置，排除已删除的记录
        $list = Db::name('api_paylist')
            ->where('delete_time', 'NULL')
            ->field('id,name,payname,type,code,mchid,min,max,status,sort,create_time')
            ->order('sort desc,id desc')
            ->select();
        
        // 隐藏敏感信息（密钥不返回）
        foreach ($list as &$item) {
            unset($item['appkey']);
            unset($item['appid']);
            unset($item['delete_time']);
            unset($item['update_time']);
        }
        
        return returnJson(200, '查询成功', [
            'list' => $list,
            'count' => count($list)
        ]);
    }
}
