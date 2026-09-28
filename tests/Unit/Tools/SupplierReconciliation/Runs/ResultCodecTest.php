<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Runs;

use App\Tools\SupplierReconciliation\Matching\ReconciliationEngine;
use App\Tools\SupplierReconciliation\Runs\ResultCodec;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Tools\SupplierReconciliation\Support\Transactions;

class ResultCodecTest extends TestCase
{
    public function test_results_survive_a_round_trip(): void
    {
        $result = (new ReconciliationEngine)->reconcile(
            Transactions::statement([
                ['INV-1', '01/08/2026', '100.00'],
                ["Line\nbreak \"quoted\"", '02/08/2026', '-5.00'],
                ['INV-3', '03/08/2026', '30.00'],
            ]),
            Transactions::ledger([
                ['INV-1', '01/08/2026', '100.00'],
                ['INV-3', '03/08/2026', '31.00'],
            ]),
        );

        $codec = new ResultCodec;
        $decoded = $codec->decode($codec->encode($result));

        $this->assertSame($result->toArray(), $decoded->toArray());
    }

    public function test_empty_results_round_trip(): void
    {
        $result = (new ReconciliationEngine)->reconcile([], []);
        $codec = new ResultCodec;

        $this->assertSame([], $codec->decode($codec->encode($result))->items);
    }
}
