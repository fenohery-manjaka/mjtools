<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Normalization;

use App\Tools\SupplierReconciliation\Normalization\DateOrder;
use App\Tools\SupplierReconciliation\Normalization\DateParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateParserTest extends TestCase
{
    /**
     * @return array<string, array{string, DateOrder, string}>
     */
    public static function validDates(): array
    {
        return [
            'day first slash' => ['12/08/2026', DateOrder::DayFirst, '2026-08-12'],
            'month first slash' => ['08/12/2026', DateOrder::MonthFirst, '2026-08-12'],
            'iso' => ['2026-08-12', DateOrder::DayFirst, '2026-08-12'],
            'iso ignores order' => ['2026-08-12', DateOrder::MonthFirst, '2026-08-12'],
            'day first dash' => ['12-08-2026', DateOrder::DayFirst, '2026-08-12'],
            'day first dot' => ['12.08.2026', DateOrder::DayFirst, '2026-08-12'],
            'two digit year' => ['12/08/26', DateOrder::DayFirst, '2026-08-12'],
            'single digits' => ['2/8/2026', DateOrder::DayFirst, '2026-08-02'],
            'english month name' => ['12 Aug 2026', DateOrder::DayFirst, '2026-08-12'],
            'english month dashes' => ['12-Aug-26', DateOrder::DayFirst, '2026-08-12'],
            'english long form' => ['August 12, 2026', DateOrder::DayFirst, '2026-08-12'],
            'french month name' => ['12 août 2026', DateOrder::DayFirst, '2026-08-12'],
            'french abbreviated' => ['1er févr. 2026', DateOrder::DayFirst, '2026-02-01'],
            'datetime' => ['2026-08-12 00:00:00', DateOrder::DayFirst, '2026-08-12'],
            'datetime day first' => ['12/08/2026 14:30', DateOrder::DayFirst, '2026-08-12'],
            'compact' => ['20260812', DateOrder::DayFirst, '2026-08-12'],
            'excel serial' => ['46246', DateOrder::DayFirst, '2026-08-12'],
        ];
    }

    #[DataProvider('validDates')]
    public function test_it_parses_common_formats(string $raw, DateOrder $order, string $expected): void
    {
        $parsed = (new DateParser)->parse($raw, $order);

        $this->assertNull($parsed->error, "Unexpected error for [{$raw}]");
        $this->assertSame($expected, $parsed->date?->toIso());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDates(): array
    {
        return [
            'text' => ['yesterday'],
            'impossible day' => ['31/02/2026'],
            'impossible month' => ['12/13/2026'],
            'amount' => ['1240.00'],
            'small integer' => ['42'],
        ];
    }

    #[DataProvider('invalidDates')]
    public function test_it_rejects_invalid_dates(string $raw): void
    {
        $parsed = (new DateParser)->parse($raw, DateOrder::DayFirst);

        $this->assertNull($parsed->date);
        $this->assertNotNull($parsed->error);
    }

    public function test_it_reports_order_evidence(): void
    {
        $parser = new DateParser;

        $this->assertSame('dmy', $parser->orderEvidence('25/08/2026'));
        $this->assertSame('mdy', $parser->orderEvidence('08/25/2026'));
        $this->assertSame('either', $parser->orderEvidence('05/08/2026'));
        $this->assertSame('unambiguous', $parser->orderEvidence('2026-08-05'));
        $this->assertSame('invalid', $parser->orderEvidence('soon'));
    }

    public function test_date_differences_are_counted_in_days(): void
    {
        $parser = new DateParser;
        $a = $parser->parse('12/08/2026')->date;
        $b = $parser->parse('13/08/2026')->date;
        $c = $parser->parse('01/03/2026')->date;
        $d = $parser->parse('28/02/2026')->date;

        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertNotNull($c);
        $this->assertNotNull($d);
        $this->assertSame(1, $a->daysBetween($b));
        $this->assertSame(1, $c->daysBetween($d));
        $this->assertSame('12 Aug 2026', $a->format());
    }
}
