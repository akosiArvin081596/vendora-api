<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientCostLayersException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(
            "Insufficient cost layers for product #{$productId}: requested {$requested}, available {$available}."
        );
    }
}
