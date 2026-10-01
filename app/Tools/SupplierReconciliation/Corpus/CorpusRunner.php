<?php

namespace App\Tools\SupplierReconciliation\Corpus;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Import\FileImporter;
use App\Tools\SupplierReconciliation\Import\HeaderDetector;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Mapping\ColumnDetector;
use App\Tools\SupplierReconciliation\Mapping\ColumnMapping;
use App\Tools\SupplierReconciliation\Mapping\CurrencyCheck;
use App\Tools\SupplierReconciliation\Mapping\PreflightCheck;
use App\Tools\SupplierReconciliation\Mapping\PreparedSide;
use App\Tools\SupplierReconciliation\Matching\ReconciliationEngine;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\ReconciliationResult;
use App\Tools\SupplierReconciliation\Review\ReviewApplier;
use RuntimeException;

/**
 * Runs a labelled corpus of real-looking files through the same path as the
 * product — import, header and column detection, preflight, engine — with no
 * human correction unless declared, and measures the result (spec §48, §49).
 *
 * Each case folder holds a statement file, a ledger file (csv, txt or xlsx)
 * and expected.json:
 * - "currency": currency confirmed for the case;
 * - "pairs": true correspondences, as [[statement rows], [ledger rows]] (file row numbers);
 * - "expect": expected engine status of some lines, by side and file row number;
 * - "balance": expected statement balance check status (verified, inconsistent, unavailable);
 * - "mapping": optional corrections a user would make, by side;
 * - "ready": false when the preflight check must block (default true).
 */
final class CorpusRunner
{
    public function __construct(
        private readonly FileImporter $importer = new FileImporter,
        private readonly ReconciliationEngine $engine = new ReconciliationEngine,
    ) {}

    /**
     * @return list<string> Case folder names, sorted.
     */
    public function cases(string $directory): array
    {
        $cases = array_map('basename', glob(rtrim($directory, '/\\').'/*', GLOB_ONLYDIR) ?: []);
        $cases = array_values(array_filter($cases, fn (string $case): bool => is_file("{$directory}/{$case}/expected.json")));
        sort($cases);

        return $cases;
    }

    /**
     * @return array{expected: array<string, mixed>, statement: PreparedSide, ledger: PreparedSide, report: array<string, mixed>, proposal: array{code: ?string, message: string}, result: ?ReconciliationResult}
     */
    public function run(string $directory, string $case): array
    {
        $dir = rtrim($directory, '/\\').'/'.$case;
        $expected = json_decode((string) file_get_contents($dir.'/expected.json'), true, flags: JSON_THROW_ON_ERROR);

        $statement = $this->prepare(Side::Statement, $this->file($dir, 'statement'), $expected['mapping']['statement'] ?? []);
        $ledger = $this->prepare(Side::Ledger, $this->file($dir, 'ledger'), $expected['mapping']['ledger'] ?? []);
        $report = (new PreflightCheck)->check($statement, $ledger, $expected['currency'] ?? null);

        return [
            'expected' => $expected,
            'statement' => $statement,
            'ledger' => $ledger,
            'report' => $report,
            'proposal' => (new CurrencyCheck)->propose($statement, $ledger),
            'result' => $report['ready']
                ? $this->engine->reconcile($statement->built->transactions, $ledger->built->transactions)
                : null,
        ];
    }

    /**
     * Figures of one case: lines, share cleared automatically, automatic
     * matches (and how many are wrong), proposals and exceptions.
     *
     * @return array<string, int|float|string|null>
     */
    public function measure(string $directory, string $case): array
    {
        $run = $this->run($directory, $case);
        $result = $run['result'];

        if ($result === null) {
            return ['case' => $case, 'ready' => 'blocked', 'lines' => null, 'cleared_percent' => null, 'automatic' => null, 'false_automatic' => null, 'proposals' => null, 'exceptions' => null, 'labelled_ok' => null, 'balance' => $run['report']['balance']['status']];
        }

        $summary = (new ReviewApplier)->apply($result, [])->summary();
        $counts = array_count_values(array_map(fn ($item): string => $item->status->value, $result->items));
        $statuses = self::statuses($result);
        $labelled = 0;
        $labelledOk = 0;

        foreach (['statement', 'ledger'] as $side) {
            foreach ($run['expected']['expect'][$side] ?? [] as $row => $status) {
                $labelled++;
                $labelledOk += ($statuses[$side][(int) $row] ?? null) === $status ? 1 : 0;
            }
        }

        return [
            'case' => $case,
            'ready' => 'yes',
            'lines' => $summary['analyzed_lines'],
            'cleared_percent' => $summary['cleared_automatically_percent'],
            'automatic' => $counts[ItemStatus::Matched->value] ?? 0,
            'false_automatic' => count(self::falseAutomaticMatches($result, $run['expected']['pairs'] ?? [])),
            'proposals' => ($counts[ItemStatus::PossibleMatch->value] ?? 0) + ($counts[ItemStatus::Ambiguous->value] ?? 0),
            'exceptions' => $summary['attention_items'],
            'labelled_ok' => "{$labelledOk}/{$labelled}",
            'balance' => $run['report']['balance']['status'],
        ];
    }

    /**
     * Automatic matches that are not labelled true pairs.
     *
     * @param  list<array{0: list<int>, 1: list<int>}>  $pairs
     * @return list<string>
     */
    public static function falseAutomaticMatches(ReconciliationResult $result, array $pairs): array
    {
        $true = array_map(fn (array $pair): string => self::key($pair[0], $pair[1]), $pairs);
        $false = [];

        foreach ($result->items as $item) {
            if ($item->status !== ItemStatus::Matched) {
                continue;
            }

            $key = self::key(
                array_map(fn (string $id): int => $result->transaction($id)->rowNumber, $item->statementIds),
                array_map(fn (string $id): int => $result->transaction($id)->rowNumber, $item->ledgerIds),
            );

            if (! in_array($key, $true, true)) {
                $false[] = $key;
            }
        }

        return $false;
    }

    /**
     * Engine status of the item containing each line, by side and file row.
     *
     * @return array{statement: array<int, string>, ledger: array<int, string>}
     */
    public static function statuses(ReconciliationResult $result): array
    {
        $statuses = ['statement' => [], 'ledger' => []];

        foreach ($result->items as $item) {
            foreach ([...$item->statementIds, ...$item->ledgerIds] as $id) {
                $transaction = $result->transaction($id);
                $statuses[$transaction->side->value][$transaction->rowNumber] = $item->status->value;
            }
        }

        return $statuses;
    }

    /**
     * @param  list<int>  $statementRows
     * @param  list<int>  $ledgerRows
     */
    private static function key(array $statementRows, array $ledgerRows): string
    {
        sort($statementRows);
        sort($ledgerRows);

        return 'S'.implode('+', $statementRows).' = L'.implode('+', $ledgerRows);
    }

    private function file(string $dir, string $name): string
    {
        foreach (['csv', 'txt', 'tsv', 'xlsx'] as $extension) {
            if (is_file("{$dir}/{$name}.{$extension}")) {
                return "{$dir}/{$name}.{$extension}";
            }
        }

        throw new RuntimeException("No {$name} file in {$dir}.");
    }

    /**
     * @param  array<string, mixed>  $corrections
     */
    private function prepare(Side $side, string $path, array $corrections): PreparedSide
    {
        $raw = $this->importer->import($path);
        $header = (new HeaderDetector)->detect($raw);
        $table = ImportedTable::fromRaw($raw, $header);
        $mapping = (new ColumnDetector)->suggest($table, $side, $header);

        if ($corrections !== []) {
            $mapping = ColumnMapping::fromArray([...$mapping->toArray(), ...$corrections]);
        }

        return PreparedSide::prepare($side, $table, $mapping);
    }
}
