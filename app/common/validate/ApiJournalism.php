<?php
declare (strict_types = 1);

namespace app\common\validate;

use think\Validate;
class ApiJournalism extends Validate
{
    protected $rule = ['title' => 'require','content' => 'require','image' => 'require','sort' => 'require|number',
    ];

    protected $message = ['title.require' => '标题为必填项','content.require' => '内容为必填项','image.require' => '图片为必填项','sort.require' => '排序 降序为必填项','sort.number' => '排序 降序需为数字',
    ];

    /**
     * 添加
     */
    public function sceneAdd()
    {
        return $this->only(['title','content','image','sort',]);
    }

    /**
     * 编辑
     */
    public function sceneEdit()
    {
        return $this->only(['image','sort',]);
    }
}
