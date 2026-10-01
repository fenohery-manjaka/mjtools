<?php

namespace App\Tools\SupplierReconciliation\Export;

use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Result\Reason;
use App\Tools\SupplierReconciliation\Review\ReviewedItem;
use App\Tools\SupplierReconciliation\Review\ReviewedResult;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Turns a reviewed result into a table usable outside the product (spec §36).
 * One line per linked statement/ledger pair; lines of the same item share
 * the item id. No matching logic lives here.
 */
final class ResultExporter
{
    public const HEADERS = [
        'Item',
        'Status',
        'Decided by',
        'Engine status',
        'Match type',
        'Confidence',
        'Summary',
        'Statement row',
        'Statement reference',
        'Statement date',
        'Statement amount',
        'Ledger row',
        'Ledger reference',
        'Ledger date',
        'Ledger amount',
        'Difference',
        'Currency',
        'Reasons',
        'Human decision',
    ];

    /**
     * @return list<list<string>>
     */
    public function rows(ReviewedResult $result, ?string $currency = null): array
    {
        $rows = [];

        foreach ($result->items as $item) {
            $statement = array_map(fn (string $id): Transaction => $result->engine->transaction($id), $item->statementIds);
            $ledger = array_map(fn (string $id): Transaction => $result->engine->transaction($id), $item->ledgerIds);
            $lines = max(count($statement), count($ledger), 1);

            for ($i = 0; $i < $lines; $i++) {
                $rows[] = [
                    $item->id,
                    $item->status->label(),
                    $item->resolution->label(),
                    $item->engineStatus->label(),
                    $item->kind?->label() ?? '',
                    $item->engineItemId === null ? '' : ($result->engine->item($item->engineItemId)?->confidence()?->label() ?? ''),
                    $item->headline,
                    ...$this->transactionColumns($statement[$i] ?? null),
                    ...$this->transactionColumns($ledger[$i] ?? null),
                    $i === 0 ? ($item->difference?->toDecimal() ?? '') : '',
                    $currency ?? '',
                    $i === 0 ? $this->reasons($item) : '',
                    $i === 0 && $item->decision !== null ? trim($item->decision->action->label().' '.$item->decision->decidedAt) : '',
                ];
            }
        }

        return $rows;
    }

    public function csv(ReviewedResult $result, ExportContext $context = new ExportContext): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to create the export.');
        }

        // BOM so that spreadsheet software opens accented text correctly.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::HEADERS, ',', '"', '');

        foreach ($this->rows($result, $context->currency) as $row) {
            fputcsv($handle, array_map($this->neutralizeFormula(...), $row), ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Writes an XLSX workbook to the given path: every result line, the open
     * items only (the work list), and a summary of the reconciliation.
     */
    public function xlsx(ReviewedResult $result, string $path, ExportContext $context = new ExportContext): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $rows = $this->rows($result, $context->currency);
        $this->tableSheet($writer->getCurrentSheet(), 'Results');
        $this->writeTable($writer, $rows);

        $open = array_values(array_filter($rows, fn (array $row): bool => in_array($row[0], $this->openItemIds($result), true)));
        $this->tableSheet($writer->addNewSheetAndMakeItCurrent(), 'To review');
        $this->writeTable($writer, $open);

        $summary = $writer->addNewSheetAndMakeItCurrent();
        $summary->setName('Summary');
        $summary->setColumnWidth(48, 1);
        $summary->setColumnWidth(60, 2);

        foreach ($this->summaryRows($result, $context) as $index => $row) {
            $writer->addRow($this->textRow($row, $index === 0 || $row[1] === '' ? $this->bold() : null));
        }

        $writer->close();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function writeTable(Writer $writer, array $rows): void
    {
        $writer->addRow($this->textRow(self::HEADERS, $this->bold()));

        foreach ($rows as $row) {
            $writer->addRow($this->textRow($row));
        }

        $writer->getCurrentSheet()->setAutoFilter(new AutoFilter(0, 1, count(self::HEADERS) - 1, max(1, count($rows) + 1)));
    }

    private function tableSheet(Sheet $sheet, string $name): void
    {
        $sheet->setName($name);
        $sheet->setSheetView((new SheetView)->withFreezeRow(2));
        $sheet->setColumnWidth(10, 1, 8, 12);
        $sheet->setColumnWidth(18, 2, 3, 4, 5, 6, 9, 10, 11, 13, 14, 15, 16, 17);
        $sheet->setColumnWidth(48, 7, 18, 19);
    }

    /**
     * @return list<string>
     */
    private function openItemIds(ReviewedResult $result): array
    {
        return array_values(array_map(
            fn (ReviewedItem $item): string => $item->id,
            array_filter($result->items, fn (ReviewedItem $item): bool => $item->needsAttention()),
        ));
    }

    private function bold(): Style
    {
        return (new Style)->withFontBold(true);
    }

    /**
     * @return list<list<string>>
     */
    public function summaryRows(ReviewedResult $result, ExportContext $context = new ExportContext): array
    {
        $summary = $result->summary();
        /** @var array<string, int> $engine */
        $engine = $summary['engine_counts'];
        /** @var array<string, int> $resolutions */
        $resolutions = $summary['resolutions'];
        /** @var array<string, array{count: int, total: string}> $amounts */
        $amounts = $summary['amounts'];
        /** @var array{items: int, lines: int} $auto */
        $auto = $summary['matched_automatically'];

        return [
            ['Supplier statement reconciliation', ''],
            ['Generated', $context->generatedAt ?? ''],
            ['Supplier statement file', $context->statementFile ?? ''],
            ['Ledger file', $context->ledgerFile ?? ''],
            ['Currency (amounts are never converted)', $context->currency ?? ''],
            ['Statement balance check', $context->balance === null ? 'Not available' : ucfirst($context->balance['status'])],
            ['Statement balance details', $context->balance['message'] ?? ''],
            ['', ''],
            ['Engine result', ''],
            ['Lines analysed', (string) $summary['analyzed_lines']],
            ['Lines matched automatically', (string) $auto['lines']],
            ['Share of lines cleared automatically (%)', (string) $summary['cleared_automatically_percent']],
            ['Items needing attention after the engine', (string) $summary['engine_attention_items']],
            ['Items still needing attention', (string) $summary['attention_items']],
            ['Possible matches (engine)', (string) $engine['possible_match']],
            ['Ambiguous (engine)', (string) $engine['ambiguous']],
            ['Missing in ledger (engine)', (string) $engine['missing_in_ledger']],
            ['Ledger only (engine)', (string) $engine['ledger_only']],
            ['Amount mismatches (engine)', (string) $engine['amount_mismatch']],
            ['Duplicates suspected (engine)', (string) $engine['duplicate_suspected']],
            ['Review required (engine)', (string) $engine['review_required']],
            ['Note', 'The share cleared automatically measures the checking work avoided, not an accuracy score.'],
            ['', ''],
            ['Your review (recorded separately from the engine)', ''],
            ['Confirmed by you', (string) $resolutions['confirmed']],
            ['Matched manually by you', (string) $resolutions['manual_match']],
            ['Rejected by you', (string) $resolutions['rejected']],
            ['Left for review', (string) $resolutions['deferred']],
            ['', ''],
            ['Amounts in open exceptions (not added together)', ''],
            ['Open invoices missing in ledger (total)', $amounts['missing_invoices']['total']],
            ['Open credits missing in ledger (total)', $amounts['missing_credits']['total']],
            ['Open amount differences (total)', $amounts['amount_differences']['total']],
            ['Open ledger-only lines (total)', $amounts['ledger_only']['total']],
        ];
    }

    /**
     * Plain numbers become numeric cells; everything else is a text cell
     * (OpenSpout would otherwise turn "=..." into a formula).
     *
     * @param  list<string>  $values
     */
    private function textRow(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(
            fn (string $value): Cell => preg_match('/^-?\d{1,15}(\.\d{1,4})?$/', $value) === 1
                ? new NumericCell(str_contains($value, '.') ? (float) $value : (int) $value, $style)
                : new StringCell($value, $style),
            $values,
        ));
    }

    /**
     * @return list<string>
     */
    private function transactionColumns(?Transaction $transaction): array
    {
        if ($transaction === null) {
            return ['', '', '', ''];
        }

        return [
            (string) $transaction->rowNumber,
            $transaction->original('reference') ?? '',
            $transaction->original('date') ?? '',
            $transaction->amount?->toDecimal() ?? ($transaction->original('amount') ?? ''),
        ];
    }

    private function reasons(ReviewedItem $item): string
    {
        return implode(' | ', array_map(fn (Reason $reason): string => $reason->message, $item->reasons));
    }

    /**
     * Prevents spreadsheet formula injection (=, +, -, @ ...), except for
     * plain negative numbers.
     */
    public function neutralizeFormula(string $value): string
    {
        if ($value === '' || preg_match('/^-\d+(\.\d+)?$/', $value) === 1) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
