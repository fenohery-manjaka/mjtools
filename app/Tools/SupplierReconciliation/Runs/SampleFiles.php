<?php

namespace App\Tools\SupplierReconciliation\Runs;

use App\Tools\SupplierReconciliation\Domain\Side;

/**
 * The fictional supplier statement and ledger offered to try the checker
 * without one's own files. They deliberately contain every kind of result:
 * exact and normalized matches, a possible match, an ambiguity, a duplicate,
 * an amount mismatch, missing invoice and credit, ledger-only lines, balance
 * lines and a free-text footer.
 */
final class SampleFiles
{
    public static function path(Side $side): string
    {
        return dirname(__DIR__).'/resources/samples/'.$side->value.'.csv';
    }

    public static function name(Side $side): string
    {
        return match ($side) {
            Side::Statement => 'sample-supplier-statement.csv',
            Side::Ledger => 'sample-ledger.csv',
        };
    }
}
