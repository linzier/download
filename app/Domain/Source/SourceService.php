<?php

namespace App\Domain\Source;

use App\Domain\Target\Target;
use App\Foundation\Client\API;

/**
 * 源数据服务
 */
class SourceService
{
    /**
     * 获取数据
     */
    public function fetch(ISource $source, Target $target)
    {
        // 获取数据
        $source->fetch(new API(), $target->type());
    }
}
