<?php

namespace App\Tools\SupplierReconciliation\Normalization;

use App\Tools\SupplierReconciliation\Domain\Amount;

/**
 * Parses human-formatted amounts ("1 240,00", "(1,240.00)", "1240.00 CR"...)
 * into exact amounts. The sign is always preserved.
 *
 * The decimal separator is normally resolved for the whole column (see
 * {@see self::separatorEvidence()}); when it is not given, values whose
 * separator is ambiguous ("1,240") are rejected rather than guessed.
 */
final class AmountParser
{
    private const MAX_INTEGER_DIGITS = 13;

    private const MAX_DECIMALS = 4;

    private const CURRENCY_CODES = 'EUR|USD|GBP|CHF|CAD|AUD|NZD|JPY|CNY|HKD|SGD|INR|ZAR|SEK|NOK|DKK|PLN|CZK|HUF|RON|MGA|MUR|XOF|XAF|MAD|TND|AED';

    public function parse(?string $raw, ?DecimalSeparator $separator = null): ParsedAmount
    {
        $value = $this->clean($raw);

        if ($value === '') {
            return ParsedAmount::empty();
        }

        [$negative, $value] = $this->extractSign($value);

        if ($value === null) {
            return ParsedAmount::error('conflicting sign markers');
        }

        if (preg_match('/^[0-9][0-9 .,\']*$/', $value) !== 1 && preg_match('/^[.,][0-9]+$/', $value) !== 1) {
            return ParsedAmount::error('not a number');
        }

        $value = str_replace([' ', "'"], '', $value);
        $decimalMark = $this->resolveDecimalMark($value, $separator);

        if ($decimalMark === false) {
            return ParsedAmount::error('ambiguous decimal separator');
        }

        $thousandsMark = $decimalMark === '.' ? ',' : '.';

        if ($decimalMark !== null && substr_count($value, $decimalMark) > 1) {
            return ParsedAmount::error('more than one decimal separator');
        }

        [$integer, $fraction] = $decimalMark !== null && str_contains($value, $decimalMark)
            ? explode($decimalMark, $value, 2)
            : [$value, ''];

        if (str_contains($fraction, $thousandsMark)) {
            return ParsedAmount::error('thousands separator after the decimal separator');
        }

        if ($integer !== '' && str_contains($integer, $thousandsMark)
            && preg_match('/^\d{1,3}(\\'.$thousandsMark.'\d{3})+$/', $integer) !== 1) {
            return ParsedAmount::error('inconsistent digit grouping');
        }

        $integer = str_replace([',', '.'], '', $integer);
        $integer = $integer === '' ? '0' : $integer;
        $fraction = rtrim($fraction, '0');

        if (strlen($fraction) > self::MAX_DECIMALS) {
            return ParsedAmount::error('more than 4 decimal places');
        }

        if (strlen(ltrim($integer, '0')) > self::MAX_INTEGER_DIGITS) {
            return ParsedAmount::error('amount too large');
        }

        $integer = ltrim($integer, '0');
        $amount = Amount::fromDecimal(($integer === '' ? '0' : $integer).($fraction === '' ? '' : '.'.$fraction));

        return ParsedAmount::ok($negative ? $amount->negate() : $amount);
    }

    /**
     * Tells which decimal separator a single value implies.
     *
     * @return 'dot'|'comma'|'ambiguous'|'none'|'invalid'
     */
    public function separatorEvidence(?string $raw): string
    {
        $value = $this->clean($raw);
        [, $value] = $this->extractSign($value);

        if ($value === null || $value === '') {
            return 'invalid';
        }

        $value = str_replace([' ', "'"], '', $value);

        if (preg_match('/^[0-9.,]+$/', $value) !== 1) {
            return 'invalid';
        }

        return match ($this->resolveDecimalMark($value, null)) {
            '.' => 'dot',
            ',' => 'comma',
            false => 'ambiguous',
            null => 'none',
        };
    }

    private function clean(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $value = preg_replace('/[\x{00A0}\x{202F}\x{2009}\x{2007}]/u', ' ', $raw) ?? $raw;
        $value = str_replace("\u{2212}", '-', $value);
        $value = trim($value);

        // Currency symbols and ISO codes around the number.
        $value = preg_replace('/[€$£¥₹]|\b(?:'.self::CURRENCY_CODES.')\b/iu', '', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return array{0: bool, 1: ?string}
     */
    private function extractSign(string $value): array
    {
        $markers = 0;
        $negative = false;

        if (preg_match('/^(.*?)\s*\b(CR|DR)\.?$/i', $value, $m) === 1) {
            $markers++;
            $negative = strtoupper($m[2]) === 'CR';
            $value = trim($m[1]);
        }

        if (preg_match('/^\((.*)\)$/', $value, $m) === 1) {
            $markers++;
            $negative = true;
            $value = trim($m[1]);
        }

        if (str_starts_with($value, '-') || str_ends_with($value, '-')) {
            $markers++;
            $negative = true;
            $value = trim($value, ' -');
        } elseif (str_starts_with($value, '+')) {
            $value = ltrim($value, '+ ');
        }

        return [$negative, $markers > 1 ? null : $value];
    }

    /**
     * @return '.'|','|null|false null when there is no separator, false when ambiguous
     */
    private function resolveDecimalMark(string $value, ?DecimalSeparator $separator): string|null|false
    {
        $hasDot = str_contains($value, '.');
        $hasComma = str_contains($value, ',');

        if (! $hasDot && ! $hasComma) {
            return null;
        }

        if ($separator !== null) {
            return $separator === DecimalSeparator::Dot ? '.' : ',';
        }

        if ($hasDot && $hasComma) {
            return strrpos($value, '.') > strrpos($value, ',') ? '.' : ',';
        }

        $mark = $hasDot ? '.' : ',';

        if (substr_count($value, $mark) > 1) {
            // Repeated mark can only be a thousands separator.
            return $mark === '.' ? ',' : '.';
        }

        $decimals = strlen($value) - strrpos($value, $mark) - 1;

        // "1,240" or "1.240": thousands grouping or three decimals — cannot tell.
        if ($decimals === 3 && strpos($value, $mark) > 0) {
            return false;
        }

        return $mark;
    }
}
