<?php

namespace App\Tools\SupplierReconciliation\Domain;

enum Side: string
{
    case Statement = 'statement';
    case Ledger = 'ledger';

    public function label(): string
    {
        return match ($this) {
            self::Statement => 'Supplier statement',
            self::Ledger => 'Ledger',
        };
    }

    public function other(): self
    {
        return $this === self::Statement ? self::Ledger : self::Statement;
    }

    public function idPrefix(): string
    {
        return $this === self::Statement ? 'S' : 'L';
    }
}
