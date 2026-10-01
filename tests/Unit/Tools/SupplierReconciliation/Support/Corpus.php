<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Support;

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
use RuntimeException;

/**
 * Labelled corpus of real-looking files (tests/Fixtures/SupplierReconciliation/corpus).
 *
 * Each case folder holds a statement file, a ledger file and expected.json:
 * - "currency": currency confirmed for the case;
 * - "pairs": true correspondences, as [[statement rows], [ledger rows]] (file row numbers);
 * - "expect": expected engine status of some lines, by side and file row number;
 * - "mapping": optional corrections a user would make, by side;
 * - "balance": expected statement balance check status (verified, inconsistent, unavailable);
 * - "ready": false when the preflight check must block (default true).
 *
 * Files go through the same path as the product: import, header and column
 * detection, preflight, engine — with no human correction unless declared.
 */
final class Corpus
{
    public const DIRECTORY = __DIR__.'/../../../../Fixtures/SupplierReconciliation/corpus';

    /**
     * @return array<string, array{string}>
     */
    public static function cases(): array
    {
        $cases = [];

        foreach (glob(self::DIRECTORY.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $cases[basename($dir)] = [basename($dir)];
        }

        ksort($cases);

        return $cases;
    }

    /**
     * @return array{expected: array<string, mixed>, statement: PreparedSide, ledger: PreparedSide, report: array<string, mixed>, proposal: array{code: ?string, message: string}, result: ?ReconciliationResult}
     */
    public static function run(string $case): array
    {
        $dir = self::DIRECTORY.'/'.$case;
        $expected = json_decode((string) file_get_contents($dir.'/expected.json'), true, flags: JSON_THROW_ON_ERROR);

        $statement = self::prepare(Side::Statement, self::file($dir, 'statement'), $expected['mapping']['statement'] ?? []);
        $ledger = self::prepare(Side::Ledger, self::file($dir, 'ledger'), $expected['mapping']['ledger'] ?? []);
        $report = (new PreflightCheck)->check($statement, $ledger, $expected['currency']);

        return [
            'expected' => $expected,
            'statement' => $statement,
            'ledger' => $ledger,
            'report' => $report,
            'proposal' => (new CurrencyCheck)->propose($statement, $ledger),
            'result' => $report['ready']
                ? (new ReconciliationEngine)->reconcile($statement->built->transactions, $ledger->built->transactions)
                : null,
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

    private static function file(string $dir, string $name): string
    {
        foreach (['csv', 'xlsx', 'txt'] as $extension) {
            if (is_file("{$dir}/{$name}.{$extension}")) {
                return "{$dir}/{$name}.{$extension}";
            }
        }

        throw new RuntimeException("No {$name} file in {$dir}.");
    }

    /**
     * @param  array<string, mixed>  $corrections
     */
    private static function prepare(Side $side, string $path, array $corrections): PreparedSide
    {
        $raw = (new FileImporter)->import($path);
        $header = (new HeaderDetector)->detect($raw);
        $table = ImportedTable::fromRaw($raw, $header);
        $mapping = (new ColumnDetector)->suggest($table, $side, $header);

        if ($corrections !== []) {
            $mapping = ColumnMapping::fromArray([...$mapping->toArray(), ...$corrections]);
        }

        return PreparedSide::prepare($side, $table, $mapping);
    }
}
