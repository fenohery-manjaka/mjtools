<?php

namespace App\Tools\SupplierReconciliation\Import;

/**
 * Cells of a sheet exactly as read, as text. Row numbers are 1-based, as in
 * a spreadsheet; blank rows are kept so numbers stay faithful to the file.
 */
final readonly class RawTable
{
    /**
     * @param  list<list<string>>  $rows
     * @param  array<string, string>  $details  Format details shown to the user (delimiter, encoding, sheet).
     */
    public function __construct(
        public FileFormat $format,
        public array $rows,
        public array $details = [],
    ) {}

    public function columnCount(): int
    {
        return $this->rows === [] ? 0 : max(array_map('count', $this->rows));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['format' => $this->format->value, 'rows' => $this->rows, 'details' => $this->details];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            format: FileFormat::from((string) $data['format']),
            rows: array_values(array_map(
                fn (mixed $row): array => array_values(array_map('strval', (array) $row)),
                (array) $data['rows'],
            )),
            details: array_map('strval', (array) ($data['details'] ?? [])),
        );
    }
}
