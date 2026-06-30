<?php

namespace App\Domain\Source;

use App\Domain\Target\Target;
use App\Foundation\Client\API;

/**
 * Source Data Service
 */
class SourceService
{
    /**
     * Fetch data
     */
    public function fetch(ISource $source, Target $target)
    {
        // Fetch data
        $source->fetch(new API(), $target->type());
    }
}
