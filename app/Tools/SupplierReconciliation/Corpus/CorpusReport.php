<?php

namespace App\Tools\SupplierReconciliation\Corpus;

use Illuminate\Console\Command;

/**
 * Measures the engine on a labelled corpus (spec §48, §49): share of lines
 * cleared automatically, automatic matches that are wrong, proposals and
 * exceptions. Real anonymised files can be measured by putting them in the
 * same layout in another folder.
 */
class CorpusReport extends Command
{
    protected $signature = 'supplier-reconciliation:corpus {directory? : Folder of labelled cases (defaults to the test corpus)}';

    protected $description = 'Measure the reconciliation engine on a labelled corpus of files';

    public function handle(CorpusRunner $runner): int
    {
        $directory = (string) ($this->argument('directory') ?? base_path('tests/Fixtures/SupplierReconciliation/corpus'));

        if (! is_dir($directory)) {
            $this->error("No such folder: {$directory}");

            return self::FAILURE;
        }

        $rows = array_map(fn (string $case): array => $runner->measure($directory, $case), $runner->cases($directory));

        if ($rows === []) {
            $this->warn('No labelled case (folder with expected.json) found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Case', 'Ready', 'Lines', 'Cleared %', 'Auto matches', 'Wrong auto', 'Proposals', 'To review', 'Labels OK', 'Balance'],
            array_map(fn (array $row): array => array_map(fn ($value): string => $value === null ? '—' : (string) $value, array_values($row)), $rows),
        );

        $wrong = array_sum(array_map(fn (array $row): int => (int) $row['false_automatic'], $rows));
        $lines = array_sum(array_map(fn (array $row): int => (int) $row['lines'], $rows));
        $cleared = $lines === 0 ? 0 : round(array_sum(array_map(fn (array $row): float => (float) $row['cleared_percent'] * (int) $row['lines'], $rows)) / $lines, 1);

        $this->line("Overall: {$lines} lines, {$cleared}% cleared automatically, {$wrong} wrong automatic match(es).");

        return $wrong === 0 ? self::SUCCESS : self::FAILURE;
    }
}
