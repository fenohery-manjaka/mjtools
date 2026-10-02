<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Normalization;

use App\Tools\SupplierReconciliation\Normalization\CurrencyDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CurrencyDetectorTest extends TestCase
{
    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function writtenCurrencies(): array
    {
        return [
            'code after amount' => ['1 240,00 EUR', 'EUR', ['EUR']],
            'code before amount' => ['GBP 1,240.00', 'GBP', ['GBP']],
            'lower case code' => ['1240.00 usd', 'USD', ['USD']],
            'euro symbol' => ['€1,240.00', '€', ['EUR']],
            'pound symbol' => ['£ 96.10', '£', ['GBP']],
            'us dollar is not singapore dollar' => ['US$ 50.00', 'US$', ['USD']],
            'brazilian real' => ['R$ 1.240,00', 'R$', ['BRL']],
            'plain dollar stays ambiguous' => ['$1,240.00', '$', ['USD', 'CAD', 'AUD', 'NZD', 'SGD', 'HKD']],
            'header with code' => ['Amount (GBP)', 'GBP', ['GBP']],
            'currency column value' => ['CHF', 'CHF', ['CHF']],
        ];
    }

    /**
     * @param  list<string>  $codes
     */
    #[DataProvider('writtenCurrencies')]
    public function test_it_reads_the_written_currency(string $text, string $label, array $codes): void
    {
        $mark = (new CurrencyDetector)->detect($text);

        $this->assertNotNull($mark);
        $this->assertSame($label, $mark->label);
        $this->assertSame($codes, $mark->codes);
    }

    public function test_plain_numbers_and_words_carry_no_currency(): void
    {
        $detector = new CurrencyDetector;

        $this->assertNull($detector->detect('1,240.00'));
        $this->assertNull($detector->detect('-420,00'));
        $this->assertNull($detector->detect('Amount'));
        $this->assertNull($detector->detect('EUROPE'));
        $this->assertNull($detector->detect(null));
    }

    public function test_an_ambiguous_symbol_allows_each_compatible_code(): void
    {
        $mark = (new CurrencyDetector)->detect('$10.00');

        $this->assertNotNull($mark);
        $this->assertTrue($mark->allows('CAD'));
        $this->assertTrue($mark->allows('usd'));
        $this->assertFalse($mark->allows('EUR'));
        $this->assertFalse($mark->isExact());
    }
}
