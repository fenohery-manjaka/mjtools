<?php

namespace Tests\Unit\Tools\SupplierReconciliation;

use App\Tools\SupplierReconciliation\Corpus\CorpusRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Labelled corpus of realistic files (tests/Fixtures/SupplierReconciliation/corpus),
 * run end to end with the mapping proposed automatically. The decisive
 * assertion is that no automatic match pairs lines that do not belong
 * together (spec §48).
 */
class CorpusTest extends TestCase
{
    private const DIRECTORY = __DIR__.'/../../../Fixtures/SupplierReconciliation/corpus';

    /**
     * @return array<string, array{string}>
     */
    public static function cases(): array
    {
        $cases = [];

        foreach ((new CorpusRunner)->cases(self::DIRECTORY) as $case) {
            $cases[$case] = [$case];
        }

        return $cases;
    }

    #[DataProvider('cases')]
    public function test_no_false_automatic_match(string $case): void
    {
        $run = (new CorpusRunner)->run(self::DIRECTORY, $case);

        if (($run['expected']['ready'] ?? true) === false) {
            $this->assertFalse($run['report']['ready'], 'The preflight check should block this case.');

            return;
        }

        $this->assertTrue($run['report']['ready'], implode("\n", $run['report']['blocking']));
        $this->assertNotNull($run['result']);
        $this->assertSame([], CorpusRunner::falseAutomaticMatches($run['result'], $run['expected']['pairs']), 'Automatic matches that are not true pairs.');
    }

    #[DataProvider('cases')]
    public function test_labelled_lines_get_the_expected_status(string $case): void
    {
        $run = (new CorpusRunner)->run(self::DIRECTORY, $case);

        if ($run['result'] === null) {
            $this->assertFalse($run['expected']['ready'] ?? true);

            return;
        }

        $statuses = CorpusRunner::statuses($run['result']);

        foreach (['statement', 'ledger'] as $side) {
            foreach ($run['expected']['expect'][$side] ?? [] as $row => $status) {
                $this->assertSame($status, $statuses[$side][(int) $row] ?? null, "{$side} row {$row}");
            }
        }
    }

    #[DataProvider('cases')]
    public function test_the_statement_balance_check_gives_the_expected_status(string $case): void
    {
        $run = (new CorpusRunner)->run(self::DIRECTORY, $case);

        if (! isset($run['expected']['balance'])) {
            $this->assertArrayHasKey('balance', $run['report']);

            return;
        }

        $this->assertSame($run['expected']['balance'], $run['report']['balance']['status'], $run['report']['balance']['message']);
    }

    public function test_the_corpus_is_not_empty_and_measurable(): void
    {
        $runner = new CorpusRunner;
        $cases = $runner->cases(self::DIRECTORY);

        $this->assertGreaterThanOrEqual(5, count($cases));

        $figures = $runner->measure(self::DIRECTORY, $cases[0]);
        $this->assertSame(0, $figures['false_automatic']);
        $this->assertGreaterThan(0, $figures['cleared_percent']);
    }
}
