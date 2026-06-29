<?php

namespace App\Domain\Source;

use App\Foundation\Client\API;

/**
 * 数据源接口
 */
interface ISource
{
    // 单源模式（针对单表格）
    public const SOURCE_TYPE_SIMPLE = 1;
    // 多源模式（针对多表格或者多 tab）
    public const SOURCE_TYPE_MULTI = 2;
    // 两次拉取之间默认时间间隔，单位毫秒
    public const DEFAULT_INTERVAL = 100;

    /**
     * 源文件名称（包含目录）
     */
    public function fileName(): string;

    /**
     * 数据源列表
     * @return array
     */
    public function srcs(): array;

    /**
     * 数据记录数（行数）
     */
    public function count(): int;

    /**
     * 源文件大小，单位字节
     */
    public function size(): int;

    /**
     * 拉取源数据的时间间隔
     * @return int
     */
    public function interval(): int;

    /**
     * 从源拉取数据并保存到本地
     * @param API $invoker 源数据调用程序
     * @param string $targetType 目标文件类型
     */
    public function fetch(API $invoker, string $targetType);
}
