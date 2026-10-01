<?php

namespace App\Tools\SupplierReconciliation\Normalization;

/**
 * Finds the currency written next to amounts ("€1,240.00", "1 240,00 EUR",
 * "Amount (GBP)"). Amounts are never converted: a currency only tells which
 * lines may be compared with each other.
 *
 * A symbol can stand for several currencies ("$" for USD, CAD, AUD...): the
 * result lists every compatible ISO code instead of guessing one.
 */
final class CurrencyDetector
{
    /** ISO 4217 codes recognised in amounts (also stripped by AmountParser). */
    public const CODE_PATTERN = 'EUR|USD|GBP|CHF|CAD|AUD|NZD|JPY|CNY|HKD|SGD|INR|ZAR|SEK|NOK|DKK|PLN|CZK|HUF|RON|BRL|MGA|MUR|XOF|XAF|MAD|TND|AED';

    /** Ordered from the most specific: "US$" before "S$", "R$" and "A$" before "$". */
    private const SYMBOLS = [
        'US$' => ['USD'],
        'R$' => ['BRL'],
        'A$' => ['AUD'],
        'C$' => ['CAD'],
        'NZ$' => ['NZD'],
        'S$' => ['SGD'],
        'HK$' => ['HKD'],
        '$' => ['USD', 'CAD', 'AUD', 'NZD', 'SGD', 'HKD'],
        '€' => ['EUR'],
        '£' => ['GBP'],
        '¥' => ['JPY', 'CNY'],
        '₹' => ['INR'],
    ];

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return explode('|', self::CODE_PATTERN);
    }

    /**
     * Currency written in an amount cell or a currency column: an ISO code or
     * a symbol. Null when the text carries no currency.
     */
    public function detect(?string $text): ?CurrencyMark
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        if (preg_match('/(?<![A-Z])('.self::CODE_PATTERN.')(?![A-Z])/u', mb_strtoupper($text), $match) === 1) {
            return new CurrencyMark($match[1], [$match[1]]);
        }

        foreach (self::SYMBOLS as $symbol => $codes) {
            if (str_contains($text, $symbol)) {
                return new CurrencyMark($symbol, $codes);
            }
        }

        return null;
    }

    /**
     * Currency announced by a column header ("Amount (EUR)", "Montant €").
     * Only explicit codes and symbols count; words are not interpreted.
     */
    public function detectInHeader(?string $header): ?CurrencyMark
    {
        return $this->detect($header);
    }
}
