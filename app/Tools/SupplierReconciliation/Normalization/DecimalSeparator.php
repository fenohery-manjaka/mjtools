<?php

namespace App\Tools\SupplierReconciliation\Normalization;

enum DecimalSeparator: string
{
    case Dot = 'dot';
    case Comma = 'comma';

    public function label(): string
    {
        return match ($this) {
            self::Dot => '1,240.50 (dot decimal)',
            self::Comma => '1 240,50 (comma decimal)',
        };
    }
}
