<?php

namespace App\Tools\SupplierReconciliation\Result;

enum MatchKind: string
{
    case Exact = 'exact';
    case Normalized = 'normalized';
    case Grouped = 'grouped';

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Exact',
            self::Normalized => 'Normalized',
            self::Grouped => 'Grouped',
        };
    }
}
