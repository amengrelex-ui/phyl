-- 修复 api_subscribe 表缺少 sum_type 字段的问题
-- 执行此SQL语句来添加缺失的字段

ALTER TABLE `api_subscribe` 
ADD COLUMN `sum_type` INT(11) NOT NULL DEFAULT 0 COMMENT '计算类型: 0=无, 1=产品激活, 2=手动领取, 3=账户验证' 
AFTER `timeline_type`;
