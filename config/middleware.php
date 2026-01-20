<?php
// 中间件配置
return [
    // 别名或分组
    'alias'    => [
        'AdminCheck' => app\common\middleware\AdminCheck::class,
        'AgentCheck' => app\common\middleware\AgentCheck::class,
        'AdminPermission'  => app\common\middleware\AdminPermission::class,
        'UserCheck' => app\common\middleware\UserCheck::class
        ],
    // 优先级设置，此数组中的中间件会按照数组中的顺序优先执行
    'priority' => [],
];
