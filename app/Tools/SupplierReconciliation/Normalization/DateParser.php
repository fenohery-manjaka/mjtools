<?php

namespace App\Tools\SupplierReconciliation\Normalization;

use App\Tools\SupplierReconciliation\Domain\CalendarDate;

/**
 * Parses common date formats. Numeric day/month order is never guessed per
 * value: it comes from the column (see {@see self::orderEvidence()}).
 */
final class DateParser
{
    /** Excel serial numbers accepted for dates (1954-10-03 .. 2119-01-10). */
    private const SERIAL_MIN = 20_000;

    private const SERIAL_MAX = 80_000;

    private const MONTHS = [
        'jan' => 1, 'january' => 1, 'janv' => 1, 'janvier' => 1,
        'feb' => 2, 'february' => 2, 'fev' => 2, 'fevr' => 2, 'fevrier' => 2,
        'mar' => 3, 'march' => 3, 'mars' => 3,
        'apr' => 4, 'april' => 4, 'avr' => 4, 'avril' => 4,
        'may' => 5, 'mai' => 5,
        'jun' => 6, 'june' => 6, 'juin' => 6,
        'jul' => 7, 'july' => 7, 'juil' => 7, 'juillet' => 7,
        'aug' => 8, 'august' => 8, 'aou' => 8, 'aout' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9, 'septembre' => 9,
        'oct' => 10, 'october' => 10, 'octobre' => 10,
        'nov' => 11, 'november' => 11, 'novembre' => 11,
        'dec' => 12, 'december' => 12, 'decembre' => 12,
    ];

    public function parse(?string $raw, DateOrder $order = DateOrder::DayFirst): ParsedDate
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return ParsedDate::empty();
        }

        // Drop a trailing time part: "2026-08-12 00:00:00", "12/08/2026 14:30".
        $value = preg_replace('/[ T]\d{1,2}:\d{2}(:\d{2}(\.\d+)?)?\s*(AM|PM|Z|[+-]\d{2}:?\d{2})?$/i', '', $value) ?? $value;

        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $value, $m) === 1) {
            return $this->build((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m) === 1) {
            return $this->build((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2}|\d{4})$/', $value, $m) === 1) {
            [$day, $month] = $order === DateOrder::DayFirst
                ? [(int) $m[1], (int) $m[2]]
                : [(int) $m[2], (int) $m[1]];

            return $this->build($this->expandYear($m[3]), $month, $day);
        }

        $words = $this->simplify($value);

        if (preg_match('/^(\d{1,2})(?:st|nd|rd|th|er)? ?([a-z]+) ?(\d{2}|\d{4})$/', $words, $m) === 1
            && ($month = self::MONTHS[$m[2]] ?? null) !== null) {
            return $this->build($this->expandYear($m[3]), $month, (int) $m[1]);
        }

        if (preg_match('/^([a-z]+) ?(\d{1,2})(?:st|nd|rd|th)? ?(\d{4})$/', $words, $m) === 1
            && ($month = self::MONTHS[$m[1]] ?? null) !== null) {
            return $this->build((int) $m[3], $month, (int) $m[2]);
        }

        if (preg_match('/^\d{5}(\.0+)?$/', $value) === 1) {
            $serial = (int) $value;

            if ($serial >= self::SERIAL_MIN && $serial <= self::SERIAL_MAX) {
                $date = (new \DateTimeImmutable('1899-12-30', new \DateTimeZone('UTC')))->modify("+{$serial} days");

                return $this->build((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
            }
        }

        return ParsedDate::error('unrecognised date format');
    }

    /**
     * Tells which numeric order a single value implies.
     *
     * @return 'dmy'|'mdy'|'either'|'unambiguous'|'invalid'
     */
    public function orderEvidence(?string $raw): string
    {
        $value = trim((string) $raw);
        $value = preg_replace('/[ T]\d{1,2}:\d{2}.*$/', '', $value) ?? $value;

        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2}|\d{4})$/', $value, $m) === 1) {
            $first = (int) $m[1];
            $second = (int) $m[2];

            $firstIsMonth = $first >= 1 && $first <= 12;
            $secondIsMonth = $second >= 1 && $second <= 12;

            return match (true) {
                $firstIsMonth && $secondIsMonth => 'either',
                $secondIsMonth => 'dmy',
                $firstIsMonth => 'mdy',
                default => 'invalid',
            };
        }

        return $this->parse($value)->date !== null ? 'unambiguous' : 'invalid';
    }

    private function build(int $year, int $month, int $day): ParsedDate
    {
        $date = CalendarDate::tryCreate($year, $month, $day);

        return $date === null ? ParsedDate::error('invalid calendar date') : ParsedDate::ok($date);
    }

    private function expandYear(string $year): int
    {
        if (strlen($year) === 4) {
            return (int) $year;
        }

        $short = (int) $year;

        return $short < 70 ? 2000 + $short : 1900 + $short;
    }

    private function simplify(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, ['é' => 'e', 'è' => 'e', 'û' => 'u', 'ô' => 'o', 'à' => 'a']);
        $value = preg_replace('/[\s\-\/.,]+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
