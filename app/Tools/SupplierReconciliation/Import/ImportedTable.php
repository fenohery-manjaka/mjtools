<?php

namespace App\Tools\SupplierReconciliation\Import;

/**
 * A raw table split into column headers and data rows, given the header row.
 */
final readonly class ImportedTable
{
    /**
     * @param  list<string>  $headers  Unique, non-empty column names.
     * @param  list<array{number: int, cells: list<string>}>  $rows  Non-blank data rows with their spreadsheet row number.
     */
    public function __construct(
        public array $headers,
        public array $rows,
        public int $headerRowNumber,
    ) {}

    public static function fromRaw(RawTable $raw, int $headerIndex): self
    {
        $headerIndex = max(0, min($headerIndex, count($raw->rows) - 1));
        $width = $raw->columnCount();
        $headers = [];
        $seen = [];

        for ($column = 0; $column < $width; $column++) {
            $name = trim(preg_replace('/\s+/u', ' ', $raw->rows[$headerIndex][$column] ?? '') ?? '');
            $name = $name === '' ? 'Column '.self::columnLetter($column) : $name;
            $key = mb_strtolower($name);

            if (isset($seen[$key])) {
                $seen[$key]++;
                $name .= ' ('.$seen[$key].')';
            } else {
                $seen[$key] = 1;
            }

            $headers[] = $name;
        }

        $rows = [];

        foreach (array_slice($raw->rows, $headerIndex + 1, null, true) as $index => $cells) {
            if (implode('', array_map('trim', $cells)) === '') {
                continue;
            }

            $rows[] = ['number' => $index + 1, 'cells' => array_pad($cells, $width, '')];
        }

        return new self($headers, $rows, $headerIndex + 1);
    }

    /**
     * @return list<string>
     */
    public function column(int $index): array
    {
        return array_map(fn (array $row): string => $row['cells'][$index] ?? '', $this->rows);
    }

    /**
     * First distinct non-empty values of a column, for previews.
     *
     * @return list<string>
     */
    public function samples(int $index, int $count = 3): array
    {
        return array_slice(array_values(array_unique(array_filter(
            $this->column($index),
            fn (string $value): bool => trim($value) !== '',
        ))), 0, $count);
    }

    public static function columnLetter(int $index): string
    {
        $letter = '';

        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letter = chr(65 + ($n - 1) % 26).$letter;
        }

        return $letter;
    }
}
