<?php

namespace App\Foundation\File;

/**
 * 文件压缩接口
 */
interface ICompress
{
    /**
     * @param string $archiveFileName 归档后文件
     * @param array $origFileNames 要归档的文件数组
     * @param bool $delOrigFile 归档后是否删除源文件
     * @return string 归档后的文件名
     */
    public function compress(string $archiveFileName, array $origFileNames, bool $delOrigFile = true): string;
}
