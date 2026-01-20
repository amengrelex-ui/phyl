<?php

use think\facade\Db;
use think\facade\Session;
use think\facade\Cookie;
// 应用公共文件
if (!function_exists('opt_photo')) {
    //图库选择
    function opt_photo($val)
    {
        return '<button class="pear-btn pear-btn-primary pear-btn-sm" style="margin:4px 5px;vertical-align:top;" id="' . $val . '" type="button">图库选择</button>
       <script>
       layui.use(["jquery"],function() {
        let $ = layui.jquery;
        //弹出窗设置 自己设置弹出百分比
        function screen() {
            if (typeof width !== "number" || width === 0) {
            width = $(window).width() * 0.8;
            }
            if (typeof height !== "number" || height === 0) {
            height = $(window).height() - 20;
            }
            return [width + "px", height + "px"];
        }
        $("#' . $val . '").on("click", function () {
            layer.open({
                type: 2,
                maxmin: true,
                title: "图库选择",
                shade: 0.1,
                area: screen(),
                content:"https://' . $_SERVER['HTTP_HOST'] . '/admin.php/index/optPhoto",
                success:function (layero,index) {
                    var iframe = window["layui-layer-iframe" + index];
                    iframe.child("' . $val . '")
                } 
            });
        });
        })
        </script>';
    }
}
if (!function_exists('rm')) {
    //清除缓存
    function rm()
    {
        delete_dir(root_path() . 'runtime');
    }
}

if (! function_exists('auth_code')) {
    /**
     * 加密解密
     * @param   string  $string     要加密的字符串或已加密的密文
     * @param   string  $operation  DECODE表示解密, ENCODE其他为加密
     * @param   string  $key        密匙
     * @param   integer $expiry     加密后有效期
     * @return  string              加密解密后的字符串
     */
    function auth_code($string, $operation = 'DECODE', $key = '', $expiry = 0)
    {
        $ckey_length = 4;                       //  动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
        $key = md5($key ? $key : 'AC_KEY');     //  密匙
        $keya = md5(substr($key, 0, 16));       //  密匙a会参与加解密
        $keyb = md5(substr($key, 16, 16));      //  密匙b会用来做数据完整性验证

        //  密匙c用于变化生成的密文
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

        //  参与运算的密匙
        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);

        /*
            明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，解密时会通过这个密匙验证数据完整性
            如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
         */
        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);

        $result = '';
        $box = range(0, 255);

        //  产生密匙簿
        $rndkey = array();
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        //  用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        //  核心加解密部分
        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256])); //  从密匙簿得出密匙进行异或，再转成字符
        }

        if ($operation == 'DECODE') {
            /*
                substr($result, 0, 10) == 0 验证数据有效性
                substr($result, 0, 10) - time() > 0 验证数据有效性
                substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16) 验证数据完整性
                验证数据有效性，请看未加密明文的格式
             */
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            /*
                把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
                因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
             */
            return $keyc . str_replace('=', '', base64_encode($result));
        }
    }
}

/**
 * 生成不重复的随机数字(不能超过10位数，否则while循环陷入死循环)
 * @param  int $start 需要生成的数字开始范围
 * @param  int $end 结束范围
 * @param  int $length 需要生成的随机数个数
 * @return number      生成的随机数
 */
function getRandNumber($start = 0, $end = 9, $length = 8)
{
    //初始化变量为0
    $count = 0;
    //建一个新数组
    $temp = array();
    while ($count < $length) {
        //在一定范围内随机生成一个数放入数组中
        $temp[] = mt_rand($start, $end);
        //$data = array_unique($temp);
        //去除数组中的重复值用了“翻翻法”，就是用array_flip()把数组的key和value交换两次。这种做法比用 array_unique() 快得多。
        $data = array_flip(array_flip($temp));
        //将数组的数量存入变量count中
        $count = count($data);
    }
    //为数组赋予新的键名
    shuffle($data);
    //数组转字符串
    $str = implode(",", $data);
    //替换掉逗号
    $number = str_replace(',', '', $str);
    return $number;
}

function curlPost($url, $post_data = array(), $timeout = 5, $header = "", $data_type = "")
{
    $header = empty($header) ? '' : $header;
    //支持json数据数据提交
    if ($data_type == 'json') {
        $post_string = json_encode($post_data);
    } elseif ($data_type == 'array') {
        $post_string = $post_data;
    } elseif (is_array($post_data)) {
        $post_string = http_build_query($post_data, '', '&');
    }

    $ch = curl_init();    // 启动一个CURL会话
    curl_setopt($ch, CURLOPT_URL, $url);     // 要访问的地址
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // 对认证证书来源的检查   // https请求 不验证证书和hosts
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // 从证书中检查SSL加密算法是否存在
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
    //curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
    //curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
    curl_setopt($ch, CURLOPT_POST, true); // 发送一个常规的Post请求
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);     // Post提交的数据包
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);     // 设置超时限制防止死循环
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    //curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);     // 获取的信息以文件流的形式返回
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['content-type: application/x-www-form-urlencoded;charset=UTF-8']); //模拟的header头
    $result = curl_exec($ch);

    // 打印请求的header信息
    //$a = curl_getinfo($ch);
    //var_dump($a);

    curl_close($ch);
    return $result;
}

if (!function_exists('get_sign_key')) {
    function get_sign_key($data, $keys)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            if ($value) $str .= $key . '=' . $value . '&';
        }
        return strtoupper(md5($str . 'key=' . $keys));
    }
}

if (!function_exists('get_sign_md5_key')) {
    function get_sign_md5_key($data, $keys)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            if ($value) $str .= $key . '=' . $value . '&';
        }
        return md5($str . 'key=' . $keys);
    }
}

if (!function_exists('is_url')) {
    //是否
    function is_url($url)
    {
        if (preg_match("/^http(s)?:\\/\\/.+/", $url)) return $url;
    }
}

if (!function_exists('rand_string')) {
    /**
     *  随机数
     *
     * @param string $length 长度
     * @param string $type   类型
     * @return void
     */
    function rand_string($length = '32', $type = 4): string
    {
        $rand = '';
        switch ($type) {
            case '1':
                $randstr = '0123456789';
                break;
            case '2':
                $randstr = 'abcdefghijklmnopqrstuvwxyz';
                break;
            case '3':
                $randstr = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            default:
                $randstr = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
        }
        $max = strlen($randstr) - 1;
        mt_srand((float)microtime() * 1000000);
        for ($i = 0; $i < $length; $i++) {
            $rand .= $randstr[mt_rand(0, $max)];
        }
        return $rand;
    }
}

if (!function_exists('set_password')) {
    //密码截取
    function set_password($password): string
    {
        return substr(md5($password), 3, -3);
    }
}

/**
 * 数据签名认证
 */
function data_sign($data = [])
{
    if (!is_array($data)) {
        $data = (array)$data;
    }
    ksort($data);
    $code = http_build_query($data);
    $sign = sha1($code);
    return $sign;
}

/**
 * 修改网站配置文件
 */
if (!function_exists('set_web')) {
    function set_web($data = [])
    {
        $str = "<?php\r\n/**\r\n * 系统配置文件\r\n */\r\nreturn [\r\n";
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $str .= getArrTree($key, $value);
            } else {
                $str .= "\t'$key' => '$value',";
                $str .= "\r\n";
            }
        }
        $str .= '];';
        @file_put_contents(config_path() . 'web.php', $str);
    }
}

if (!function_exists('get_arr_tree')) {
    /**
     * 递归配置数组
     */
    function get_arr_tree($key, $data, $level = "\t")
    {
        $i = "$level'$key' => [\r\n";
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $i .= get_arr_tree($k, $v, $level . "\t");
            } else {
                $i .= "$level\t'$k' => '$v',";
                $i .= "\r\n";
            }
        }
        return  $i . "$level" . '],' . "\r\n";
    }
}

if (!function_exists('aes_encrypt')) {
    /**
     *
     * @param string $string 需要加密的字符串
     * @param string $key 密钥
     * @return string
     */
    function aes_encrypt($string, $key = "ONSPEED"): string
    {
        $data = openssl_encrypt($string, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return strtolower(bin2hex($data));
    }
}

if (!function_exists('aes_decrypt')) {
    /**
     * @param string $string 需要解密的字符串
     * @param string $key 密钥
     * @return string
     */
    function aes_decrypt($string, $key = "ONSPEED"): string
    {
        try {
            return openssl_decrypt(hex2bin($string), 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('get_field')) {
    /**
     * 获取指定表指定行指定字段
     * @param  string       $tn      完整表名
     * @param  string|array $where   参数数组或者id值
     * @param  string       $field   字段名,默认'name'
     * @param  string       $default 获取失败的默认值,默认''
     * @param  array        $order   排序数组
     * @return string                获取到的内容
     */
    function get_field($tn, $where, $field = 'name', $default = '', $order = ['id' => 'desc'])
    {
        if (!is_array($where)) {
            $where = ['id' => $where];
        }
        $row = \think\facade\Db::name($tn)->field([$field])->where($where)->order($order)->find();
        return $row === null ? $default : $row[$field];
    }
}

if (!function_exists('delete_dir')) {
    /**
     * 遍历删除文件夹所有内容
     * @param  string $dir 要删除的文件夹
     */
    function delete_dir($dir)
    {
        $dh = opendir($dir);
        while ($file = readdir($dh)) {
            if ($file != '.' && $file != '..') {
                $filepath = $dir . '/' . $file;
                if (is_dir($filepath)) {
                    delete_dir($filepath);
                } else {
                    @unlink($filepath);
                }
            }
        }
        closedir($dh);
        @rmdir($dir);
    }
}

if (!function_exists('get_tree')) {
    /**
     * 递归无限级分类权限
     * @param array $data
     * @param int $pid
     * @param string $field1 父级字段
     * @param string $field2 子级关联的父级字段
     * @param string $field3 子级键值
     * @return mixed
     */
    function get_tree($data, $pid = 0, $field1 = 'id', $field2 = 'pid', $field3 = 'children')
    {
        $arr = [];
        foreach ($data as $k => $v) {
            if ($v[$field2] == $pid) {
                $v[$field3] = get_tree($data, $v[$field1]);
                $arr[] = $v;
            }
        }
        return $arr;
    }
}

if (!function_exists('hump_underline')) {
    /**
     * 驼峰转下划线
     * @param  string $str 需要转换的字符串
     * @return string      转换完毕的字符串
     */
    function hump_underline($str)
    {
        return strtolower(trim(preg_replace('/[A-Z]/', '_\\0', $str), '_'));
    }
}

if (!function_exists('underline_hump')) {
    /**
     * 下划线转驼峰
     * @param  string $str 需要转换的字符串
     * @return string      转换完毕的字符串
     */
    function underline_hump($str)
    {
        return ucfirst(
            preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $str)
        );
    }
}

if (!function_exists('record_log')) {
    /**
     * @记录日志
     * @param [type] $param
     * @param string $file
     *
     * @return void
     */
    function record_log($param, $file = '')
    {
        $path = root_path() . 'log/' . $file . "/";
        if (!is_dir($path)) @mkdir($path, 0777, true);
        if (is_array($param)) {
            $param = json_encode($param, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE);
        }
        @file_put_contents(
            $path . date("Y_m_d", time()) . ".txt",
            "执行日期：" . "\r\n" . date('Y-m-d H:i:s', time()) . ' ' . "\n" . $param . "\r\n",
            FILE_APPEND
        );
    }
}

/**
 * @abstract 返回json
 * @param $code
 * @param $msg
 * @param array $data
 * @param int $bend
 * @return bool|false|string
 */
function returnJson($code, $msg, $data = [], $bend = 0)
{
    $arr['code'] = $code;
    $arr['msg'] = $msg;
    if (in_array('', $arr)) {
        return false;
    }
    if (!empty($data)) {
        $arr['data'] = $data;
    }
    if ($bend == 0) {
        return json_encode($arr, JSON_UNESCAPED_UNICODE);
    } else {
        return json($arr);
    }
}


function checkClicks($user_id = null)
{
    if ($user_id) {
        $cacheKey = 'last_submit_time_' . $user_id;
        $diff = 3; // 限制间隔（秒）
        $current_time = time();
        $last_submit_time = cache($cacheKey);
        if (!$last_submit_time || ($current_time - $last_submit_time) >= $diff) {
            cache($cacheKey, $current_time);
            return false;
        }
        return true;
    }
}
/**
 * @abstract app生成邀请码
 * @param string|int $user_id 用户id
 * @return string 生成的邀请码
 */
function encode_Invite($user_id)
{
    return $user_id + 999998;

    static $source_string = 'E5FCDG3HQA4B1NOPIJ2RSTUV67MWX89KLYZ';
    $num = $user_id;
    $code = '';

    while ($num > 0) {
        $mod = $num % 35;
        $num = ($num - $mod) / 35;
        $code = $source_string[$mod] . $code;
    }

    if (empty($code[3])) {
        $code = str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    return $code;
}

/**
 * @abstract app邀请码解码
 * @param string $code 邀请码
 * @return float|int 返回值为id值
 */
function decode_Invite($code)
{
    return $code - 999998;

    static $source_string = 'E5FCDG3HQA4B1NOPIJ2RSTUV67MWX89KLYZ';
    if (strrpos($code, '0') !== false) {
        $code = substr($code, strrpos($code, '0') + 1);
    }

    $len = strlen($code);
    $code = strrev($code);
    $num = 0;
    for ($i = 0; $i < $len; $i++) {
        $num += strpos($source_string, $code[$i]) * pow(35, $i);
    }

    return $num;
}

/**
 * 生成token
 */
function create_token()
{
    $key = md5(time() . mt_rand(1000, 9999) . time());
    $key = base64_encode($key);

    return md5($key);
}

/**
 * 随机生成要求位数个字符
 * @param length 规定几位字符
 */
function getRandChar($length)
{
    $str = null;
    $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"; //大小写字母以及数字
    //    $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";//大小写字母以及数字
    $max = strlen($strPol) - 1;

    for ($i = 0; $i < $length; $i++) {
        $str .= $strPol[rand(0, $max)];
    }
    return $str;
}

function get_rand($proArr)
{
    $result = '';

    //概率数组的总概率精度
    $proSum = array_sum($proArr); //计算数组中元素的和

    //概率数组循环
    foreach ($proArr as $key => $proCur) {
        $randNum = mt_rand(1, $proSum);
        if ($randNum <= $proCur) { //如果这个随机数小于等于数组中的一个元素，则返回数组的下标
            $result = $key;
            break;
        } else {
            $proSum -= $proCur;
        }
    }

    unset($proArr);

    return $result;
}

/**
 * 提取字符串的数字
 * @param $str
 * @return string|string[]|null
 */
function string_number($str)
{
    return preg_replace('/[^\.0123456789]/s', '', $str);
}

function numberToChinese($num, $flag = false)
{
    $chineseNumbers = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];
    $chineseUnits = ['', '拾', '佰', '仟', '万', '拾', '佰', '仟', '亿', '拾', '佰', '仟'];
    // 检查是否为数字
    if (!is_numeric($num)) {
        return 0;
    }
    // 转换成整数，去掉小数点
    $num = (string)(int)$num;
    $len = strlen($num);
    $result = '';
    // 遍历每一位数字
    for ($i = 0; $i < $len; $i++) {
        $n = (int)$num[$i];
        $unit = $chineseUnits[$len - $i - 1];

        // 如果数字是 0，且不在千位、万位、亿位上，则直接跳过
        if ($n === 0) {
            if ($result && $result[-3] !== '零' && $i != $len - 1) {
                $result .= '零';
            }
            continue;
        }

        $result .= $chineseNumbers[$n] . $unit;
    }
    // 处理最后的“零”字符
    $result = rtrim($result, '零');
    // 添加“整”字
    if (!$flag) {
        $result .= '元整';
    } else {
        $result .= '元';
    }
    return $result;
}

function get_data_type()
{
    return [
        '1' => '申请提款',
        '2' => '充值',
        '3' => '扣除',
        '4' => '转账支出',
        '5' => '转账收入',
        '6' => '注册赠送',
        '7' => '系统扣款', //'后台手动充值',
        '8' => '一代奖励',
        '9' => '二代奖励',
        '10' => '三代奖励',
        '11' => '签到金',
        '12' => '余额宝转入',
        '13' => '余额宝转出',
        '14' => '爱心捐赠',
        '15' => '代金券',
        '16' => '产品收益',
        '17' => '本金返还',
        '18' => '每月任务奖励',
        '19' => '补卡',
        '20' => '养老金',
        '21' => '待提余额',
        '22' => '司法认证费',
        '23' => '个人所得税',
        '24' => '办理联名卡',
        '25' => '养老钱包',
        '26' => '扣除代金券',
        '27' => '养老金收益',
        '28' => '释放待提余额',
        '29' => '钱包收益',
        '30' => '可提余额',
        '31' => '办理银行卡',
        '99' => '医疗补助资格券',
    ];
}

function get_receive_cates()
{
    return Db::name('api_level')->where('id', '>', '0')->cache(600)->select();
}


function get_fun_income_profit($amount)
{
    $array = [
        ['id' => 0,'name'=>'A','min' => 0, 'max' => 1000000, 'profit' => 5000],
        ['id' => 1,'name'=>'B','min' => 1000001, 'max' => 3000000, 'profit' => 15000],
        ['id' => 2,'name'=>'C', 'min' => 3000001, 'max' => 10000000, 'profit' => 30000],
        ['id' => 3,'name'=>'D', 'min' => 10000001, 'max' => 9999999999, 'profit' => 50000],
    ];
    foreach ($array as $income) {
        if ($amount >= $income['min'] && $amount <= $income['max']) {
            unset($income['min'],$income['max']);
            return $income;
        }
    }
    return [];
}

function get_fun_pension_profit($amount)
{
    $array = [
        ['id' => 0, 'min' => 1000000, 'max' => 3000000, 'profit' => 10],
        ['id' => 1, 'min' => 3000001, 'max' => 10000000, 'profit' => 20],
        ['id' => 2, 'min' => 10000001, 'max' => 9999999999, 'profit' => 30],
    ];
    foreach ($array as $pension) {
        if ($amount >= $pension['min'] && $amount <= $pension['max']) {
            return $pension['profit'];
        }
    }
    return 0;
}

function get_fun_wallet_profit($amount)
{
    $array = [
        ['id' => 0, 'min' => 0, 'max' => 1000000, 'profit' => 190],
        ['id' => 1, 'min' => 1000001, 'max' => 3000000, 'profit' => 290],
        ['id' => 2, 'min' => 3000001, 'max' => 10000000, 'profit' => 360],
        ['id' => 3, 'min' => 10000001, 'max' => 9999999999, 'profit' => 520],
    ];
    foreach ($array as $wallet) {
        if ($amount >= $wallet['min'] && $amount <= $wallet['max']) {
            return $wallet['profit'];
        }
    }
    return 0;
}

function get_fun_apply_bank_profit($amount)
{
    $array = [
        ['id' => 0,'name'=>'理财金卡','image'=>'/static/mine/bank/0.jpg','min' => 0, 'max' => 200000, 'profit' => 3000],
        ['id' => 1,'name'=>'理财白金卡','image'=>'/static/mine/bank/1.jpg','min' => 200001, 'max' => 500000, 'profit' => 5000],
        ['id' => 2,'name'=>'财富卡','image'=>'/static/mine/bank/2.jpg', 'min' => 500001, 'max' => 1000000, 'profit' => 8000],
        ['id' => 3,'name'=>'私人银行卡','image'=>'/static/mine/bank/3.jpg', 'min' => 1000001, 'max' => 9999999999, 'profit' => 10000],
    ];
    foreach ($array as $bank) {
        if ($amount >= $bank['min'] && $amount <= $bank['max']) {
            unset($bank['min'],$bank['max']);
            return $bank;
        }
    }
    return [];
}

/*
*重新登录
*/

function re_login()
{
    //获取后台登录用户信息
    $userinfo = Db::name('admin_admin')->where('id', Session::get('admin.id'))->find();
    //确定已经登录的情况下,如果用户最后的更新时间距离现在大于10800秒
    if (!empty($userinfo)) {
        if (time() - strtotime($userinfo['update_time']) >= 10800) {
            Session::delete('admin');
            Cookie::delete('token');
            Cookie::delete('sign');
        }
    }
}
