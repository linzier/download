<?php

namespace App\Domain\Source;

use App\Foundation\Client\API;

/**
 * Data Source Interface
 */
interface ISource
{
    // Single source mode (for single table)
    public const SOURCE_TYPE_SIMPLE = 1;
    // Multi-source mode (for multiple tables or tabs)
    public const SOURCE_TYPE_MULTI = 2;
    // Default time interval between two fetches, in milliseconds
    public const DEFAULT_INTERVAL = 100;

    /**
     * Source file name (including directory)
     */
    public function fileName(): string;

    /**
     * Data source list
     * @return array
     */
    public function srcs(): array;

    /**
     * Number of data records (rows)
     */
    public function count(): int;

    /**
     * Source file size in bytes
     */
    public function size(): int;

    /**
     * Time interval for fetching source data
     * @return int
     */
    public function interval(): int;

    /**
     * Fetch data from the source and save it locally
     * @param API $invoker Source data invoker
     * @param string $targetType Target file type
     */
    public function fetch(API $invoker, string $targetType);
}
