<?php

namespace App\Tools\SupplierReconciliation\Import;

use DateInterval;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Comment\TextRun;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use ZipArchive;

/**
 * Reads the first non-empty worksheet of an XLSX workbook as text.
 * Date cells become ISO dates, numbers keep a plain dot-decimal form.
 */
final class XlsxReader
{
    public function read(string $path, ImportLimits $limits): RawTable
    {
        $this->guardArchive($path, $limits);

        // Empty rows are preserved so row numbers match the workbook.
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];

                foreach ($sheet->getRowIterator() as $row) {
                    if (count($rows) > $limits->maxRows) {
                        throw ImportException::tooManyRows($limits->maxRows);
                    }

                    $count = $row->getNumCells();

                    if ($count > $limits->maxColumns) {
                        throw ImportException::tooManyColumns($limits->maxColumns);
                    }

                    $cells = [];

                    for ($i = 0; $i < $count; $i++) {
                        $cells[] = isset($row->cells[$i]) ? $this->cellToString($row->cells[$i]) : '';
                    }

                    while ($cells !== [] && trim((string) end($cells)) === '') {
                        array_pop($cells);
                    }

                    $rows[] = $cells;
                }

                if ($this->hasContent($rows)) {
                    return new RawTable(FileFormat::Xlsx, $rows, ['sheet' => $sheet->getName()]);
                }
            }
        } catch (OpenSpoutException) {
            throw ImportException::corrupted();
        } finally {
            $reader->close();
        }

        throw ImportException::empty();
    }

    private function guardArchive(string $path, ImportLimits $limits): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw ImportException::corrupted();
        }

        try {
            if ($zip->locateName('xl/workbook.xml') === false) {
                throw ImportException::notText();
            }

            $total = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $total += $stat === false ? 0 : (int) $stat['size'];

                if ($total > $limits->maxUncompressedBytes) {
                    throw ImportException::tooLarge($limits->maxBytes);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function cellToString(Cell $cell): string
    {
        // Formulas use their cached result. OpenSpout also reads text starting with "="
        // as a formula without result: keep that text rather than losing it.
        $value = $cell instanceof Cell\FormulaCell ? ($cell->getComputedValue() ?? $cell->getValue()) : $cell->getValue();

        return match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => $value->format('H:i:s') === '00:00:00' ? $value->format('Y-m-d') : $value->format('Y-m-d H:i:s'),
            $value instanceof DateInterval => $value->format('%H:%I:%S'),
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            is_int($value) => (string) $value,
            is_float($value) => $this->formatFloat($value),
            is_array($value) => implode('', array_map(fn (TextRun $run): string => $run->text, $value)),
            default => (string) $value,
        };
    }

    /**
     * Plain decimal form without binary noise (99.9 rather than 99.900000000000006).
     */
    private function formatFloat(float $value): string
    {
        if (floor($value) === $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }

        $text = rtrim(rtrim(sprintf('%.8F', round($value, 8)), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function hasContent(array $rows): bool
    {
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if (trim($cell) !== '') {
                    return true;
                }
            }
        }

        return false;
    }
}
