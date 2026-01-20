<?php
/**
 * 资金流水类型常量定义
 * 用于 api_fund_detail 表的 data_type 字段
 */

// 申请提款
define('DATA_TYPE_WITHDRAW', 1);

// 充值
define('DATA_TYPE_RECHARGE', 2);

// 扣除
define('DATA_TYPE_DEDUCT', 3);

// 转账支出
define('DATA_TYPE_TRANSFER_OUT', 4);

// 转账收入
define('DATA_TYPE_TRANSFER_IN', 5);

// 注册赠送
define('DATA_TYPE_REGISTER_GIFT', 6);

// 系统扣款
define('DATA_TYPE_SYSTEM_DEDUCT', 7);

// 一代奖励
define('DATA_TYPE_FIRST_GENERATION_REWARD', 8);

// 二代奖励
define('DATA_TYPE_SECOND_GENERATION_REWARD', 9);

// 三代奖励
define('DATA_TYPE_THIRD_GENERATION_REWARD', 10);

// 签到金
define('DATA_TYPE_SIGN_REWARD', 11);

// 余额宝转入
define('DATA_TYPE_YUEBAO_IN', 12);

// 余额宝转出
define('DATA_TYPE_YUEBAO_OUT', 13);

// 爱心捐赠
define('DATA_TYPE_DONATION', 14);

// 代金券
define('DATA_TYPE_VOUCHER', 15);

// 产品收益
define('DATA_TYPE_PRODUCT_PROFIT', 16);

// 本金返还
define('DATA_TYPE_PRINCIPAL_RETURN', 17);

// 每月任务奖励
define('DATA_TYPE_MONTHLY_TASK_REWARD', 18);

// 补卡
define('DATA_TYPE_CARD_REPLACEMENT', 19);

// 养老金
define('DATA_TYPE_PENSION', 20);

// 待提余额
define('DATA_TYPE_PENDING_BALANCE', 21);

// 司法认证费
define('DATA_TYPE_JUDICIAL_CERTIFICATION_FEE', 22);

// 个人所得税
define('DATA_TYPE_PERSONAL_INCOME_TAX', 23);

// 办理联名卡
define('DATA_TYPE_CO_BRANDED_CARD', 24);

// 养老钱包
define('DATA_TYPE_PENSION_WALLET', 25);

// 扣除代金券
define('DATA_TYPE_DEDUCT_VOUCHER', 26);

// 养老金收益
define('DATA_TYPE_PENSION_PROFIT', 27);

// 释放待提余额
define('DATA_TYPE_RELEASE_PENDING_BALANCE', 28);

// 钱包收益
define('DATA_TYPE_WALLET_PROFIT', 29);

// 可提余额
define('DATA_TYPE_WITHDRAWABLE_BALANCE', 30);

// 办理银行卡
define('DATA_TYPE_BANK_CARD_APPLICATION', 31);

// 医疗补助资格券
define('DATA_TYPE_MEDICAL_SUBSIDY_VOUCHER', 99);

/**
 * 获取所有类型的中文解释映射
 * @return array
 */
if (!function_exists('get_data_type_map')) {
    function get_data_type_map()
    {
        return [
            DATA_TYPE_WITHDRAW => '申请提款',
            DATA_TYPE_RECHARGE => '充值',
            DATA_TYPE_DEDUCT => '扣除',
            DATA_TYPE_TRANSFER_OUT => '转账支出',
            DATA_TYPE_TRANSFER_IN => '转账收入',
            DATA_TYPE_REGISTER_GIFT => '注册赠送',
            DATA_TYPE_SYSTEM_DEDUCT => '系统扣款',
            DATA_TYPE_FIRST_GENERATION_REWARD => '一代奖励',
            DATA_TYPE_SECOND_GENERATION_REWARD => '二代奖励',
            DATA_TYPE_THIRD_GENERATION_REWARD => '三代奖励',
            DATA_TYPE_SIGN_REWARD => '签到金',
            DATA_TYPE_YUEBAO_IN => '余额宝转入',
            DATA_TYPE_YUEBAO_OUT => '余额宝转出',
            DATA_TYPE_DONATION => '爱心捐赠',
            DATA_TYPE_VOUCHER => '代金券',
            DATA_TYPE_PRODUCT_PROFIT => '产品收益',
            DATA_TYPE_PRINCIPAL_RETURN => '本金返还',
            DATA_TYPE_MONTHLY_TASK_REWARD => '每月任务奖励',
            DATA_TYPE_CARD_REPLACEMENT => '补卡',
            DATA_TYPE_PENSION => '养老金',
            DATA_TYPE_PENDING_BALANCE => '待提余额',
            DATA_TYPE_JUDICIAL_CERTIFICATION_FEE => '司法认证费',
            DATA_TYPE_PERSONAL_INCOME_TAX => '个人所得税',
            DATA_TYPE_CO_BRANDED_CARD => '办理联名卡',
            DATA_TYPE_PENSION_WALLET => '养老钱包',
            DATA_TYPE_DEDUCT_VOUCHER => '扣除代金券',
            DATA_TYPE_PENSION_PROFIT => '养老金收益',
            DATA_TYPE_RELEASE_PENDING_BALANCE => '释放待提余额',
            DATA_TYPE_WALLET_PROFIT => '钱包收益',
            DATA_TYPE_WITHDRAWABLE_BALANCE => '可提余额',
            DATA_TYPE_BANK_CARD_APPLICATION => '办理银行卡',
            DATA_TYPE_MEDICAL_SUBSIDY_VOUCHER => '医疗补助资格券',
        ];
    }
}

/**
 * 根据类型常量获取中文解释
 * @param int $type 类型常量
 * @return string
 */
if (!function_exists('get_data_type_text')) {
    function get_data_type_text($type)
    {
        $map = get_data_type_map();
        return isset($map[$type]) ? $map[$type] : '未知类型';
    }
}
