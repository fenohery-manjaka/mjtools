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
 * Reads one worksheet of an XLSX workbook as text: the requested one, or else
 * the sheet that looks most like a list of transactions (most rows holding
 * both a date and a number), the first one winning ties. Cover pages and
 * summaries before the data are thus skipped. The names of all sheets are
 * kept so that the user can ask for another one.
 *
 * Date cells become ISO dates, numbers keep a plain dot-decimal form.
 */
final class XlsxReader
{
    private const MAX_SHEETS = 20;

    public function read(string $path, ImportLimits $limits, ?string $sheetName = null): RawTable
    {
        $this->guardArchive($path, $limits);

        // Empty rows are preserved so row numbers match the workbook.
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $names = [];
        $best = null;
        $bestScore = -1;
        $tooLarge = false;

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $names[] = $sheet->getName();

                if (count($names) > self::MAX_SHEETS || ($sheetName !== null && $sheet->getName() !== $sheetName)) {
                    continue;
                }

                $rows = [];

                foreach ($sheet->getRowIterator() as $row) {
                    // A sheet over the limit is skipped, unless it is the one asked for.
                    if (count($rows) > $limits->maxRows) {
                        $tooLarge = true;
                        $rows = null;

                        break;
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

                if ($rows === null) {
                    continue;
                }

                $score = $this->hasContent($rows) ? $this->transactionLikeRows($rows) : -1;

                if ($score > $bestScore) {
                    $best = ['name' => $sheet->getName(), 'rows' => $rows];
                    $bestScore = $score;
                }
            }
        } catch (OpenSpoutException) {
            throw ImportException::corrupted();
        } finally {
            $reader->close();
        }

        if ($sheetName !== null && ! in_array($sheetName, $names, true)) {
            throw ImportException::sheetNotFound();
        }

        if ($best === null) {
            throw $tooLarge ? ImportException::tooManyRows($limits->maxRows) : ImportException::empty();
        }

        $position = array_search($best['name'], $names, true);
        $details = ['sheet' => count($names) > 1 ? sprintf('%s (%d of %d)', $best['name'], (int) $position + 1, count($names)) : $best['name']];

        return new RawTable(FileFormat::Xlsx, $best['rows'], $details, $names);
    }

    /**
     * Rows holding at least one date and one other number: the shape of a
     * statement or ledger line.
     *
     * @param  list<list<string>>  $rows
     */
    private function transactionLikeRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $dates = 0;
            $numbers = 0;

            foreach ($row as $cell) {
                $cell = trim($cell);

                if (preg_match('/^\d{4}-\d{2}-\d{2}( |$)|^\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}$/', $cell) === 1) {
                    $dates++;
                } elseif (preg_match('/^[-(]?[\d\s.,\x{00A0}\x{202F}]*\d[\d\s.,]*\)?-?$/u', $cell) === 1) {
                    $numbers++;
                }
            }

            $count += $dates > 0 && $numbers > 0 ? 1 : 0;
        }

        return $count;
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
