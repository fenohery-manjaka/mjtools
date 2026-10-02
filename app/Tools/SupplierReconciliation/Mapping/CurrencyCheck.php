<?php

namespace App\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Normalization\CurrencyDetector;

/**
 * One reconciliation compares amounts in a single currency (spec §4, A.11).
 * The currency is proposed from what the files say, always confirmed by the
 * user, and any line written in another currency blocks the reconciliation:
 * amounts are never converted.
 */
final class CurrencyCheck
{
    /**
     * @return array{code: ?string, message: string}
     */
    public function propose(PreparedSide $statement, PreparedSide $ledger): array
    {
        $marks = [...$statement->built->currencies->marks(), ...$ledger->built->currencies->marks()];

        if ($marks === []) {
            return ['code' => null, 'message' => 'No currency is written in the files: choose the currency of both files.'];
        }

        $labels = implode(', ', array_values(array_unique(array_map(fn ($mark): string => $mark->label, $marks))));

        foreach ($marks as $mark) {
            if ($mark->codes === []) {
                return ['code' => null, 'message' => "\"{$mark->label}\" in a currency column is not a recognised currency code (EUR, USD, GBP…): choose the currency, and check that column."];
            }
        }

        $common = CurrencyDetector::codes();

        foreach ($marks as $mark) {
            $common = array_values(array_intersect($common, $mark->codes));
        }

        return match (count($common)) {
            1 => ['code' => $common[0], 'message' => "Detected in the files: {$labels}."],
            0 => ['code' => null, 'message' => "Several currencies appear in the files ({$labels}). A reconciliation compares a single currency: choose it."],
            default => ['code' => null, 'message' => "The files show {$labels}, which can stand for several currencies: choose which one."],
        };
    }

    /**
     * Blocking problems when lines or a file are in another currency.
     *
     * @return list<string>
     */
    public function problems(PreparedSide $prepared, ?string $currency): array
    {
        if ($currency === null) {
            return [];
        }

        $name = $prepared->side === Side::Statement ? 'supplier statement' : 'ledger';
        $evidence = $prepared->built->currencies;
        $problems = [];

        if ($evidence->header !== null && ! $evidence->header->allows($currency)) {
            $problems[] = "The amount column of the {$name} is labelled {$evidence->header->label}, but this reconciliation is in {$currency}. Amounts in different currencies are never compared: check the currency or the file.";
        }

        foreach ($evidence->linesNotIn($currency) as $label => $lines) {
            $what = $lines === 1 ? '1 line' : "{$lines} lines";
            $problems[] = "{$what} of the {$name} ".($lines === 1 ? 'is' : 'are')." in {$label}, but this reconciliation is in {$currency}. Amounts in different currencies are never compared: remove these lines or reconcile them separately.";
        }

        return $problems;
    }
}
