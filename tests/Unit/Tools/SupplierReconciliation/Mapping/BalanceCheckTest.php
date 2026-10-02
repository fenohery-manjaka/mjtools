<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Mapping;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Import\FileImporter;
use App\Tools\SupplierReconciliation\Import\HeaderDetector;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Mapping\AmountMode;
use App\Tools\SupplierReconciliation\Mapping\BalanceCheck;
use App\Tools\SupplierReconciliation\Mapping\ColumnMapping;
use App\Tools\SupplierReconciliation\Mapping\PreflightCheck;
use App\Tools\SupplierReconciliation\Mapping\PreparedSide;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Tools\SupplierReconciliation\Support\Files;

/**
 * Optional balance consistency (spec §4, A.12): verified, inconsistent or
 * unavailable — never guessed and never blocking.
 */
class BalanceCheckTest extends TestCase
{
    protected function tearDown(): void
    {
        Files::cleanup();
    }

    private function statement(string $csv): PreparedSide
    {
        $raw = (new FileImporter)->import(Files::text($csv));
        $table = ImportedTable::fromRaw($raw, (new HeaderDetector)->detect($raw));

        return PreparedSide::prepare(Side::Statement, $table, new ColumnMapping(0, ['reference' => 0, 'description' => 1, 'amount' => 2]));
    }

    /**
     * @return list<Transaction>
     */
    private function transactions(string $csv): array
    {
        return $this->statement($csv)->built->transactions;
    }

    public function test_opening_balance_plus_lines_equal_to_closing_balance_is_verified(): void
    {
        $result = (new BalanceCheck)->check($this->transactions("Ref,Description,Amount\n,Balance brought forward,500.00\nINV-1,Bricks,100.00\nCN-2,Return,-40.00\n,Closing balance,560.00\n"));

        $this->assertSame(BalanceCheck::VERIFIED, $result['status']);
        $this->assertSame('500.00', $result['opening']);
        $this->assertSame('60.00', $result['movements']);
        $this->assertSame('560.00', $result['closing']);
        $this->assertNull($result['difference']);
    }

    public function test_without_opening_balance_the_lines_alone_must_give_the_closing_balance(): void
    {
        $result = (new BalanceCheck)->check($this->transactions("Ref,Description,Amount\nINV-1,Bricks,100.00\nINV-2,Sand,50.00\n,Total due,150.00\n"));

        $this->assertSame(BalanceCheck::VERIFIED, $result['status']);
        $this->assertNull($result['opening']);
    }

    public function test_a_different_closing_balance_is_reported_with_the_difference(): void
    {
        $result = (new BalanceCheck)->check($this->transactions("Ref,Description,Amount\n,Opening balance,500.00\nINV-1,Bricks,100.00\n,Closing balance,700.00\n"));

        $this->assertSame(BalanceCheck::INCONSISTENT, $result['status']);
        $this->assertSame('600.00', $result['expected']);
        $this->assertSame('100.00', $result['difference']);
        $this->assertStringContainsString('differs', $result['message']);
    }

    public function test_the_check_is_unavailable_rather_than_guessed(): void
    {
        $check = new BalanceCheck;

        $noClosing = $check->check($this->transactions("Ref,Description,Amount\n,Balance brought forward,500.00\nINV-1,Bricks,100.00\n"));
        $unreadable = $check->check($this->transactions("Ref,Description,Amount\nINV-1,Bricks,abc\nINV-2,Sand,10.00\n,Closing balance,110.00\n"));
        $empty = $check->check([]);
        $closingWithoutAmount = $check->check($this->transactions("Ref,Description,Amount\nINV-1,Bricks,100.00\nTotal due,,\n"));

        $this->assertSame(BalanceCheck::UNAVAILABLE, $noClosing['status']);
        $this->assertStringContainsString('no closing balance', $noClosing['message']);
        $this->assertSame(BalanceCheck::UNAVAILABLE, $unreadable['status']);
        $this->assertSame(BalanceCheck::UNAVAILABLE, $empty['status']);
        $this->assertStringContainsString('no amount in the amount columns', $closingWithoutAmount['message']);
    }

    public function test_balances_shown_only_in_a_running_balance_column_are_used(): void
    {
        $raw = (new FileImporter)->import(Files::text("Ref,Description,Debit,Credit,Balance\n,Previous balance,,,500.00\nINV-1,Bricks,100.00,,600.00\nPAY-1,Payment,,50.00,550.00\n,Amount due,,,550.00\n"));
        $table = ImportedTable::fromRaw($raw, 0);
        $mapping = new ColumnMapping(0, ['reference' => 0, 'description' => 1, 'debit' => 2, 'credit' => 3, 'balance' => 4], AmountMode::DebitCredit);
        $built = PreparedSide::prepare(Side::Statement, $table, $mapping)->built;

        $result = (new BalanceCheck)->check($built->transactions, $built->runningBalances);

        $this->assertSame(BalanceCheck::VERIFIED, $result['status'], $result['message']);
        $this->assertSame('500.00', $result['opening']);
        $this->assertSame('550.00', $result['closing']);

        $withoutColumn = (new BalanceCheck)->check($built->transactions);
        $this->assertSame(BalanceCheck::UNAVAILABLE, $withoutColumn['status']);
        $this->assertStringContainsString('running balance column', $withoutColumn['message']);
    }

    public function test_without_balance_lines_the_running_balance_frames_the_period(): void
    {
        $raw = (new FileImporter)->import(Files::text("Ref,Description,Amount,Balance\nINV-1,Bricks,100.00,1100.00\nINV-2,Sand,50.00,1150.00\nINV-3,Lime,25.00,1175.00\n"));
        $table = ImportedTable::fromRaw($raw, 0);
        $built = PreparedSide::prepare(Side::Statement, $table, new ColumnMapping(0, ['reference' => 0, 'description' => 1, 'amount' => 2, 'balance' => 3]))->built;

        $result = (new BalanceCheck)->check($built->transactions, $built->runningBalances);

        // Opening deduced from the first line (1,100.00 - 100.00), closing from the last.
        $this->assertSame(BalanceCheck::VERIFIED, $result['status'], $result['message']);
        $this->assertSame('1,000.00', $result['opening']);
        $this->assertSame('1,175.00', $result['closing']);
    }

    public function test_an_inconsistent_balance_is_a_warning_not_a_blocker(): void
    {
        $statement = $this->statement("Ref,Description,Amount\nINV-1,Bricks,100.00\n,Closing balance,700.00\n");
        $ledger = $this->statement("Ref,Description,Amount\nINV-1,Bricks,100.00\n");

        $report = (new PreflightCheck)->check($statement, $ledger, 'EUR');

        $this->assertTrue($report['ready']);
        $this->assertSame(BalanceCheck::INCONSISTENT, $report['balance']['status']);
        $this->assertStringContainsString('closing balance', implode("\n", $report['warnings']));
    }
}
