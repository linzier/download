<?php

namespace App\Domain\Task;

class Merchant
{
    private $merchantId;
    private $merchantType;

    public function __construct(int $merchantId, int $merchantType)
    {
        $this->merchantId = $merchantId;
        $this->merchantType = $merchantType;
    }

    public function id(): int
    {
        return $this->merchantId;
    }

    public function type(): int
    {
        return $this->merchantType;
    }
}
