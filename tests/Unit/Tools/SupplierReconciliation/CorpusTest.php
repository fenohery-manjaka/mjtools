<?php

namespace Tests\Unit\Tools\SupplierReconciliation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Tools\SupplierReconciliation\Support\Corpus;

/**
 * Labelled corpus of realistic files (tests/Fixtures/SupplierReconciliation/corpus),
 * run end to end with the mapping proposed automatically. The decisive
 * assertion is that no automatic match pairs lines that do not belong
 * together (spec §48).
 */
class CorpusTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function cases(): array
    {
        return Corpus::cases();
    }

    #[DataProvider('cases')]
    public function test_no_false_automatic_match(string $case): void
    {
        $run = Corpus::run($case);

        if (($run['expected']['ready'] ?? true) === false) {
            $this->assertFalse($run['report']['ready'], 'The preflight check should block this case.');

            return;
        }

        $this->assertTrue($run['report']['ready'], implode("\n", $run['report']['blocking']));
        $this->assertNotNull($run['result']);
        $this->assertSame([], Corpus::falseAutomaticMatches($run['result'], $run['expected']['pairs']), 'Automatic matches that are not true pairs.');
    }

    #[DataProvider('cases')]
    public function test_labelled_lines_get_the_expected_status(string $case): void
    {
        $run = Corpus::run($case);

        if ($run['result'] === null) {
            $this->assertFalse($run['expected']['ready'] ?? true);

            return;
        }

        $statuses = Corpus::statuses($run['result']);

        foreach (['statement', 'ledger'] as $side) {
            foreach ($run['expected']['expect'][$side] ?? [] as $row => $status) {
                $this->assertSame($status, $statuses[$side][(int) $row] ?? null, "{$side} row {$row}");
            }
        }
    }

    #[DataProvider('cases')]
    public function test_the_statement_balance_check_gives_the_expected_status(string $case): void
    {
        $run = Corpus::run($case);

        if (! isset($run['expected']['balance'])) {
            $this->assertArrayHasKey('balance', $run['report']);

            return;
        }

        $this->assertSame($run['expected']['balance'], $run['report']['balance']['status'], $run['report']['balance']['message']);
    }

    public function test_the_corpus_is_not_empty(): void
    {
        $this->assertNotEmpty(Corpus::cases());
    }
}
