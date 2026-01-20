-- AI 医生问答对话记录表
CREATE TABLE IF NOT EXISTS `cloud_times_api_doctor_chat` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `session_id` varchar(64) NOT NULL DEFAULT '' COMMENT '会话ID',
  `question` text NOT NULL COMMENT '用户问题',
  `answer` text NOT NULL COMMENT 'AI回答',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI医生问答对话记录表';
