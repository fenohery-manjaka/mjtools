<?php

namespace App\Tools\SupplierReconciliation\Mapping;

/**
 * What a column looks like, used to propose a mapping.
 */
final readonly class ColumnProfile
{
    /**
     * @param  list<string>  $values  Sample values.
     */
    public function __construct(
        public string $header,
        public array $values,
        public float $dateRatio,
        public float $amountRatio,
        public float $filledRatio,
    ) {}
}
