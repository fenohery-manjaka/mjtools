<?php

namespace App\Tools\SupplierReconciliation\Import;

/**
 * Entry point of the import: identifies the real file type from its content
 * (not only its name) and reads it.
 */
final class FileImporter
{
    public function __construct(
        private readonly ImportLimits $limits = new ImportLimits,
        private readonly CsvReader $csv = new CsvReader,
        private readonly XlsxReader $xlsx = new XlsxReader,
    ) {}

    /**
     * @param  ?string  $sheet  Worksheet to read in a workbook; the most relevant one when null.
     */
    public function import(string $path, ?string $sheet = null): RawTable
    {
        $size = @filesize($path);

        if ($size === false) {
            throw ImportException::corrupted();
        }

        if ($size === 0) {
            throw ImportException::empty();
        }

        if ($size > $this->limits->maxBytes) {
            throw ImportException::tooLarge($this->limits->maxBytes);
        }

        $table = match ($this->detect($path)) {
            FileFormat::Xlsx => $this->xlsx->read($path, $this->limits, $sheet),
            FileFormat::Csv => $this->csv->read($path, $this->limits),
        };

        if ($this->nonEmptyRows($table) < 2) {
            throw ImportException::noTable();
        }

        return $table;
    }

    public function detect(string $path): FileFormat
    {
        $handle = fopen($path, 'rb');
        $head = $handle === false ? '' : (string) fread($handle, 8);

        if ($handle !== false) {
            fclose($handle);
        }

        return match (true) {
            str_starts_with($head, "PK\x03\x04") => FileFormat::Xlsx,
            str_starts_with($head, "\xD0\xCF\x11\xE0") => throw ImportException::unsupported('Legacy Excel (XLS)'),
            str_starts_with($head, '%PDF') => throw ImportException::unsupported('PDF'),
            str_starts_with($head, "\x89PNG"), str_starts_with($head, "\xFF\xD8\xFF"), str_starts_with($head, 'GIF8') => throw ImportException::unsupported('Image'),
            default => FileFormat::Csv,
        };
    }

    private function nonEmptyRows(RawTable $table): int
    {
        return count(array_filter(
            $table->rows,
            fn (array $row): bool => implode('', array_map('trim', $row)) !== '',
        ));
    }
}
