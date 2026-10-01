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

    public function csv(ReviewedResult $result, ?string $currency = null): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to create the export.');
        }

        // BOM so that spreadsheet software opens accented text correctly.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::HEADERS, ',', '"', '');

        foreach ($this->rows($result, $currency) as $row) {
            fputcsv($handle, array_map($this->neutralizeFormula(...), $row), ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Writes an XLSX workbook (results + summary) to the given path.
     */
    public function xlsx(ReviewedResult $result, string $path, ?string $currency = null): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Results');
        $writer->addRow($this->textRow(self::HEADERS));

        foreach ($this->rows($result, $currency) as $row) {
            $writer->addRow($this->textRow($row));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Summary');

        foreach ($this->summaryRows($result) as $row) {
            $writer->addRow($this->textRow($row));
        }

        $writer->close();
    }

    /**
     * @return list<list<string>>
     */
    public function summaryRows(ReviewedResult $result): array
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
            ['Confirmed by you', (string) $resolutions['confirmed']],
            ['Matched manually by you', (string) $resolutions['manual_match']],
            ['Rejected by you', (string) $resolutions['rejected']],
            ['Left for review', (string) $resolutions['deferred']],
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
    private function textRow(array $values): Row
    {
        return new Row(array_map(
            fn (string $value): Cell => preg_match('/^-?\d{1,15}(\.\d{1,4})?$/', $value) === 1
                ? new NumericCell(str_contains($value, '.') ? (float) $value : (int) $value, null)
                : new StringCell($value, null),
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
