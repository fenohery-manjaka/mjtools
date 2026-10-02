<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Normalization;

use App\Tools\SupplierReconciliation\Normalization\AmountParser;
use App\Tools\SupplierReconciliation\Normalization\DecimalSeparator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountParserTest extends TestCase
{
    /**
     * @return array<string, array{string, ?DecimalSeparator, string}>
     */
    public static function validAmounts(): array
    {
        return [
            'plain dot decimal' => ['1240.00', null, '1240.00'],
            'english grouping' => ['1,240.00', null, '1240.00'],
            'french grouping with space' => ['1 240,00', null, '1240.00'],
            'french grouping with non breaking space' => ["1\u{00A0}240,00", null, '1240.00'],
            'french grouping with narrow space' => ["1\u{202F}240,00", null, '1240.00'],
            'german grouping' => ['1.240,00', null, '1240.00'],
            'swiss grouping' => ["1'240.00", null, '1240.00'],
            'integer' => ['1240', null, '1240.00'],
            'one decimal' => ['1240,5', null, '1240.50'],
            'negative leading minus' => ['-420.00', null, '-420.00'],
            'negative unicode minus' => ["\u{2212}420,00", null, '-420.00'],
            'negative trailing minus' => ['420.00-', null, '-420.00'],
            'negative parentheses' => ['(1,240.00)', null, '-1240.00'],
            'credit suffix' => ['420.00 CR', null, '-420.00'],
            'debit suffix' => ['420.00 DR', null, '420.00'],
            'euro symbol' => ['€1,240.00', null, '1240.00'],
            'euro symbol after' => ['1 240,00 €', null, '1240.00'],
            'currency code' => ['EUR 1,240.00', null, '1240.00'],
            'us dollar symbol' => ['US$1,240.00', null, '1240.00'],
            'brazilian real symbol' => ['R$ 1.240,00', null, '1240.00'],
            'large amount' => ['1,234,567.89', null, '1234567.89'],
            'explicit comma column resolves ambiguity' => ['1,240', DecimalSeparator::Comma, '1.24'],
            'explicit dot column resolves grouping' => ['1,240', DecimalSeparator::Dot, '1240.00'],
            'four decimals' => ['12.3456', null, '12.3456'],
            'trailing zeros beyond two decimals' => ['1240.0000', null, '1240.00'],
            'plus sign' => ['+50.00', null, '50.00'],
            'zero' => ['0.00', null, '0.00'],
            'leading decimal' => ['.50', null, '0.50'],
        ];
    }

    #[DataProvider('validAmounts')]
    public function test_it_parses_formatted_amounts(string $raw, ?DecimalSeparator $separator, string $expected): void
    {
        $parsed = (new AmountParser)->parse($raw, $separator);

        $this->assertNull($parsed->error, "Unexpected error for [{$raw}]: {$parsed->error}");
        $this->assertSame($expected, $parsed->amount?->toDecimal());
    }

    /**
     * @return array<string, array{string, ?DecimalSeparator}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'text' => ['abc', null],
            'number followed by text' => ['12 apples', null],
            'ambiguous thousands or decimals' => ['1,240', null],
            'two decimal separators' => ['1.240.5,00,1', null],
            'dot value in comma column' => ['1240.50', DecimalSeparator::Comma],
            'bad grouping' => ['12,40,000.00', null],
            'too many decimals' => ['1.23456', null],
            'double sign markers' => ['(-420.00)', null],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_it_rejects_values_it_cannot_interpret(string $raw, ?DecimalSeparator $separator): void
    {
        $parsed = (new AmountParser)->parse($raw, $separator);

        $this->assertNull($parsed->amount, "[{$raw}] should not be accepted.");
        $this->assertNotNull($parsed->error);
    }

    public function test_empty_values_are_empty_not_errors(): void
    {
        $parser = new AmountParser;

        $this->assertTrue($parser->parse('')->isEmpty());
        $this->assertTrue($parser->parse('   ')->isEmpty());
        $this->assertTrue($parser->parse(null)->isEmpty());
    }

    public function test_the_sign_is_never_dropped(): void
    {
        $parser = new AmountParser;

        $positive = $parser->parse('420.00')->amount;
        $negative = $parser->parse('-420.00')->amount;

        $this->assertNotNull($positive);
        $this->assertNotNull($negative);
        $this->assertFalse($positive->equals($negative));
    }

    public function test_it_reports_separator_evidence(): void
    {
        $parser = new AmountParser;

        $this->assertSame('dot', $parser->separatorEvidence('1,240.50'));
        $this->assertSame('comma', $parser->separatorEvidence('1 240,50'));
        $this->assertSame('ambiguous', $parser->separatorEvidence('1,240'));
        $this->assertSame('none', $parser->separatorEvidence('1240'));
        $this->assertSame('invalid', $parser->separatorEvidence('n/a'));
    }
}
