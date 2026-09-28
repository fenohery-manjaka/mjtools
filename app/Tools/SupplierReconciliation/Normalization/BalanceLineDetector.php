<?php

namespace App\Tools\SupplierReconciliation\Normalization;

/**
 * Recognises statement lines that are balances or totals rather than
 * transactions ("Balance brought forward", "Total due"...). Only explicit
 * wording is recognised; anything uncertain stays a transaction.
 */
final class BalanceLineDetector
{
    private const PATTERN = '/^\s*(
        (opening|closing|previous|current|account|outstanding)\s+balance
        | balance\s*(b\/?f|c\/?f|brought\s+forward|carried\s+forward|forward|due|outstanding)?
        | (sub\s*)?totals?(\s+(due|outstanding|balance|amount|to\s+pay))?
        | amount\s+due
        | solde(\s+(initial|final|ant[ée]rieur|pr[ée]c[ée]dent|report[ée]|[àa]\s+payer|d[ûu]))?
        | report(\s+[àa]\s+nouveau)?
        | a\s+nouveau
        | total\s+[àa]\s+payer
    )\s*[:.]?\s*$/xiu';

    /**
     * @param  list<?string>  $texts  Reference, type and description values of the row.
     */
    public function isBalanceLine(array $texts): bool
    {
        foreach ($texts as $text) {
            if ($text !== null && trim($text) !== '' && preg_match(self::PATTERN, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
