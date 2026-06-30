<?php

namespace App\Processor\Monitor;

/**
 * Linked list node
 */
class SizeNode
{
    private $size;
    private $time;
    private $next;

    public function __construct(int $size, int $time)
    {
        $this->size = $size;
        $this->time = $time;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function time(): int
    {
        return $this->time;
    }

    public function next(): ?SizeNode
    {
        return $this->next;
    }

    public function setNext(SizeNode $node = null)
    {
        $this->next = $node;
    }
}
