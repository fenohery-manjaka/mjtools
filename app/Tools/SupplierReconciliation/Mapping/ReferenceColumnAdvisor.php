<?php

namespace App\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Normalization\ReferenceNormalizer;

/**
 * Ledgers often hold two numbers per line: an internal one ("Document No."
 * PI-001842) and the supplier's invoice number ("External Document No."
 * INV-4581). Header names cannot tell which one the statement uses; the
 * other file can. This advisor finds the column of a file that shares the
 * most references with the other file, when it does clearly better than the
 * mapped one. It only proposes: the user still confirms the mapping.
 */
final class ReferenceColumnAdvisor
{
    /** A column must share at least this many references to be proposed... */
    private const MIN_SHARED = 2;

    /** ...and at least this many times more than the mapped column. */
    private const FACTOR = 2;

    public function __construct(
        private readonly ReferenceNormalizer $references = new ReferenceNormalizer,
    ) {}

    /**
     * @param  list<Transaction>  $other  Transactions of the other file.
     * @return array{column: int, shared: int, current: int}|null
     */
    public function better(ImportedTable $table, ColumnMapping $mapping, array $other): ?array
    {
        $keys = [];

        foreach ($other as $transaction) {
            if ($transaction->reference !== null && $transaction->reference->identifying) {
                $keys[$transaction->reference->zeroKey] = true;
            }
        }

        if ($keys === []) {
            return null;
        }

        $current = $mapping->column(Field::Reference);
        $taken = array_diff_key($mapping->columns, array_flip([Field::Reference->value, Field::Description->value]));
        $scores = [];

        foreach (array_keys($table->headers) as $column) {
            if (in_array($column, $taken, true)) {
                continue;
            }

            $scores[$column] = $this->shared($table->column($column), $keys);
        }

        $currentScore = $current === null ? 0 : ($scores[$current] ?? $this->shared($table->column($current), $keys));
        arsort($scores);
        $best = array_key_first($scores);

        if ($best === null || $best === $current || $scores[$best] < self::MIN_SHARED || $scores[$best] < max(1, $currentScore) * self::FACTOR) {
            return null;
        }

        return ['column' => $best, 'shared' => $scores[$best], 'current' => $currentScore];
    }

    /**
     * Returns the mapping with the better reference column, or the mapping
     * unchanged. The column given up keeps no role (it was a reference).
     *
     * @param  list<Transaction>  $other
     */
    public function improve(ImportedTable $table, ColumnMapping $mapping, array $other): ColumnMapping
    {
        $better = $this->better($table, $mapping, $other);

        if ($better === null) {
            return $mapping;
        }

        $columns = $mapping->columns;

        // The new reference column may have been taken as the description.
        if (($columns[Field::Description->value] ?? null) === $better['column']) {
            unset($columns[Field::Description->value]);
        }

        $columns[Field::Reference->value] = $better['column'];

        return ColumnMapping::fromArray([...$mapping->toArray(), 'columns' => $columns]);
    }

    /**
     * @param  list<string>  $values
     * @param  array<string, true>  $keys
     */
    private function shared(array $values, array $keys): int
    {
        $found = [];

        foreach ($values as $value) {
            $reference = $this->references->normalize($value);

            if ($reference !== null && $reference->identifying && isset($keys[$reference->zeroKey])) {
                $found[$reference->zeroKey] = true;
            }
        }

        return count($found);
    }
}
