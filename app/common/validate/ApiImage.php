<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;
class ApiImage extends Validate
{
    protected $rule = ['title' => 'require','sort' => 'require|number','image' => 'require',
    ];

    protected $message = ['title.require' => '轮播图标题为必填项','sort.require' => '排序 降序为必填项','sort.number' => '排序 降序需为数字','image.require' => '轮播图地址为必填项',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['title','sort','image',]);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['title','sort','image',]);
    }
}
