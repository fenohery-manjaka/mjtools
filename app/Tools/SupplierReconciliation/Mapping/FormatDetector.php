<?php

namespace App\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Normalization\AmountParser;
use App\Tools\SupplierReconciliation\Normalization\DateOrder;
use App\Tools\SupplierReconciliation\Normalization\DateParser;
use App\Tools\SupplierReconciliation\Normalization\DecimalSeparator;

/**
 * Resolves number and date conventions for a whole column, so individual
 * values are never guessed one by one.
 */
final class FormatDetector
{
    public function __construct(
        private readonly AmountParser $amounts = new AmountParser,
        private readonly DateParser $dates = new DateParser,
    ) {}

    /**
     * @param  list<string>  $values
     * @return array{separator: DecimalSeparator, ambiguous: bool, conflicting: bool}
     */
    public function decimalSeparator(array $values): array
    {
        $evidence = ['dot' => 0, 'comma' => 0, 'ambiguous' => 0];

        foreach ($values as $value) {
            $kind = $this->amounts->separatorEvidence($value);

            if (isset($evidence[$kind])) {
                $evidence[$kind]++;
            }
        }

        return [
            'separator' => $evidence['comma'] > $evidence['dot'] ? DecimalSeparator::Comma : DecimalSeparator::Dot,
            'ambiguous' => $evidence['dot'] === 0 && $evidence['comma'] === 0 && $evidence['ambiguous'] > 0,
            'conflicting' => $evidence['dot'] > 0 && $evidence['comma'] > 0,
        ];
    }

    /**
     * @param  list<string>  $values
     * @return array{order: DateOrder, ambiguous: bool, conflicting: bool}
     */
    public function dateOrder(array $values): array
    {
        $evidence = ['dmy' => 0, 'mdy' => 0, 'either' => 0];

        foreach ($values as $value) {
            $kind = $this->dates->orderEvidence($value);

            if (isset($evidence[$kind])) {
                $evidence[$kind]++;
            }
        }

        return [
            'order' => $evidence['mdy'] > $evidence['dmy'] ? DateOrder::MonthFirst : DateOrder::DayFirst,
            'ambiguous' => $evidence['dmy'] === 0 && $evidence['mdy'] === 0 && $evidence['either'] > 0,
            'conflicting' => $evidence['dmy'] > 0 && $evidence['mdy'] > 0,
        ];
    }

    /**
     * Share of non-empty values that parse as dates.
     *
     * @param  list<string>  $values
     */
    public function dateRatio(array $values): float
    {
        return $this->ratio($values, fn (string $v): bool => $this->dates->parse($v)->date !== null
            || $this->dates->parse($v, DateOrder::MonthFirst)->date !== null);
    }

    /**
     * Share of non-empty values that parse as amounts.
     *
     * @param  list<string>  $values
     */
    public function amountRatio(array $values): float
    {
        return $this->ratio($values, fn (string $v): bool => $this->amounts->parse($v)->amount !== null
            || $this->amounts->parse($v, DecimalSeparator::Dot)->amount !== null
            || $this->amounts->parse($v, DecimalSeparator::Comma)->amount !== null);
    }

    /**
     * @param  list<string>  $values
     * @param  callable(string): bool  $test
     */
    private function ratio(array $values, callable $test): float
    {
        $values = array_values(array_filter($values, fn (string $v): bool => trim($v) !== ''));

        if ($values === []) {
            return 0.0;
        }

        return count(array_filter($values, $test)) / count($values);
    }
}
