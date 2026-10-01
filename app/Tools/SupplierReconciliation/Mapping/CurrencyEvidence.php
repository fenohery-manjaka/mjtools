<?php

namespace App\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Normalization\CurrencyMark;

/**
 * Currencies written in one file: on its lines (amount cells or currency
 * column) and in the header of its amount column. Lines without any currency
 * are assumed to be in the currency confirmed for the reconciliation.
 */
final readonly class CurrencyEvidence
{
    /**
     * @param  array<string, array{mark: CurrencyMark, lines: int}>  $lines  Label => mark and number of lines.
     */
    public function __construct(
        public array $lines = [],
        public ?CurrencyMark $header = null,
    ) {}

    /**
     * @return list<CurrencyMark>
     */
    public function marks(): array
    {
        $marks = array_values(array_map(fn (array $entry): CurrencyMark => $entry['mark'], $this->lines));

        return $this->header === null ? $marks : [$this->header, ...$marks];
    }

    public function isEmpty(): bool
    {
        return $this->marks() === [];
    }

    /**
     * Lines whose written currency is not the given one.
     *
     * @return array<string, int> Label => number of lines.
     */
    public function linesNotIn(string $code): array
    {
        $other = [];

        foreach ($this->lines as $label => $entry) {
            if (! $entry['mark']->allows($code)) {
                $other[$label] = $entry['lines'];
            }
        }

        return $other;
    }

    /**
     * @return list<array{label: string, lines: int|null}> For display; lines is null for the header.
     */
    public function describe(): array
    {
        $described = $this->header === null ? [] : [['label' => $this->header->label, 'lines' => null]];

        foreach ($this->lines as $label => $entry) {
            $described[] = ['label' => (string) $label, 'lines' => $entry['lines']];
        }

        return $described;
    }
}
