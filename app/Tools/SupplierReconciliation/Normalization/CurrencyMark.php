<?php

namespace App\Tools\SupplierReconciliation\Normalization;

/**
 * A currency as written in a file ("EUR", "€", "$") and the ISO codes it is
 * compatible with.
 */
final readonly class CurrencyMark
{
    /**
     * @param  list<string>  $codes
     */
    public function __construct(
        public string $label,
        public array $codes,
    ) {}

    public function allows(string $code): bool
    {
        return in_array(strtoupper($code), $this->codes, true);
    }

    public function isExact(): bool
    {
        return count($this->codes) === 1;
    }
}
