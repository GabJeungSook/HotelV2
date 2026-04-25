<?php

namespace App\Services\Pos;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public string $sourceType,
        public int $menuId,
        public float $available,
        public float $requested,
    ) {
        parent::__construct(
            "Insufficient stock for {$sourceType}#{$menuId}: have {$available}, need {$requested}"
        );
    }
}
