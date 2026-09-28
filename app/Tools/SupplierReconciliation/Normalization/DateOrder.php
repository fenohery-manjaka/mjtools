<?php

namespace App\Tools\SupplierReconciliation\Normalization;

/**
 * Order of day and month in numeric dates such as 03/04/2026.
 * ISO dates (2026-04-03) are always read year-month-day.
 */
enum DateOrder: string
{
    case DayFirst = 'dmy';
    case MonthFirst = 'mdy';

    public function label(): string
    {
        return match ($this) {
            self::DayFirst => 'Day first (31/12/2026)',
            self::MonthFirst => 'Month first (12/31/2026)',
        };
    }
}
