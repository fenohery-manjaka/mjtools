<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Support;

use DateTimeImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Temporary files for import tests.
 */
final class Files
{
    /** @var list<string> */
    private static array $created = [];

    public static function text(string $content, string $extension = 'csv'): string
    {
        $path = self::path($extension);
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * @param  list<list<string|int|float|DateTimeImmutable|null>>  $rows
     */
    public static function xlsx(array $rows, string $sheetName = 'Statement'): string
    {
        $path = self::path('xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($sheetName);
        $dateStyle = (new Style)->withFormat('dd/mm/yyyy');

        foreach ($rows as $values) {
            $cells = array_map(
                fn ($value): Cell => $value instanceof DateTimeImmutable
                    ? Cell::fromValue($value, $dateStyle)
                    : Cell::fromValue($value),
                $values,
            );
            $writer->addRow(new Row($cells));
        }

        $writer->close();

        return $path;
    }

    /**
     * A workbook with several sheets, in order.
     *
     * @param  array<string, list<list<string|int|float|null>>>  $sheets  Sheet name => rows.
     */
    public static function workbook(array $sheets): string
    {
        $path = self::path('xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        $first = true;

        foreach ($sheets as $name => $rows) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($name);
            $first = false;

            foreach ($rows as $values) {
                $writer->addRow(new Row(array_map(fn ($value): Cell => Cell::fromValue($value), $values)));
            }
        }

        $writer->close();

        return $path;
    }

    public static function cleanup(): void
    {
        foreach (self::$created as $path) {
            @unlink($path);
        }

        self::$created = [];
    }

    private static function path(string $extension): string
    {
        $path = sys_get_temp_dir().'/mjtools-test-'.bin2hex(random_bytes(6)).'.'.$extension;
        self::$created[] = $path;

        return $path;
    }
}
