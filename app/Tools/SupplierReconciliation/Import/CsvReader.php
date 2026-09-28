<?php

namespace App\Tools\SupplierReconciliation\Import;

/**
 * Reads delimited text files: detects encoding (UTF-8, UTF-16, Windows-1252)
 * and delimiter (comma, semicolon, tab, pipe).
 */
final class CsvReader
{
    private const DELIMITERS = [',' => 'comma', ';' => 'semicolon', "\t" => 'tab', '|' => 'pipe'];

    public function read(string $path, ImportLimits $limits): RawTable
    {
        $content = (string) file_get_contents($path);

        [$content, $encoding] = $this->toUtf8($content);

        if (str_contains($content, "\0")) {
            throw ImportException::notText();
        }

        if (trim($content) === '') {
            throw ImportException::empty();
        }

        $delimiter = $this->detectDelimiter($content);
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw ImportException::corrupted();
        }

        fwrite($handle, $content);
        rewind($handle);

        $rows = [];

        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if (count($rows) > $limits->maxRows) {
                fclose($handle);

                throw ImportException::tooManyRows($limits->maxRows);
            }

            $cells = array_map(fn (?string $cell): string => (string) $cell, $cells);

            while ($cells !== [] && trim((string) end($cells)) === '') {
                array_pop($cells);
            }

            if (count($cells) > $limits->maxColumns) {
                fclose($handle);

                throw ImportException::tooManyColumns($limits->maxColumns);
            }

            $rows[] = $cells;
        }

        fclose($handle);

        return new RawTable(FileFormat::Csv, $rows, [
            'encoding' => $encoding,
            'delimiter' => self::DELIMITERS[$delimiter],
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function toUtf8(string $content): array
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return [substr($content, 3), 'UTF-8'];
        }

        if (str_starts_with($content, "\xFF\xFE")) {
            return [mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE'), 'UTF-16'];
        }

        if (str_starts_with($content, "\xFE\xFF")) {
            return [mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE'), 'UTF-16'];
        }

        if (mb_check_encoding($content, 'UTF-8')) {
            return [$content, 'UTF-8'];
        }

        return [mb_convert_encoding($content, 'UTF-8', 'Windows-1252'), 'Windows-1252'];
    }

    private function detectDelimiter(string $content): string
    {
        $lines = array_slice(array_values(array_filter(
            preg_split('/\R/', $content) ?: [],
            fn (string $line): bool => trim($line) !== '',
        )), 0, 30);

        $best = ',';
        $bestScore = [0.0, 0];

        foreach (array_keys(self::DELIMITERS) as $delimiter) {
            $counts = array_map(fn (string $line): int => count(str_getcsv($line, $delimiter, '"', '')), $lines);
            $frequencies = array_count_values($counts);
            arsort($frequencies);
            $mode = (int) array_key_first($frequencies);

            if ($mode < 2) {
                continue;
            }

            $score = [$frequencies[$mode] / count($lines), $mode];

            if ($score > $bestScore) {
                $best = $delimiter;
                $bestScore = $score;
            }
        }

        return $best;
    }
}
