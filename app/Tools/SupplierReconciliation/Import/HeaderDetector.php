<?php

namespace App\Tools\SupplierReconciliation\Import;

/**
 * Finds the header row, which is often preceded by a title, an address or a
 * statement date in supplier statements.
 */
final class HeaderDetector
{
    private const SCAN_ROWS = 25;

    private const KEYWORDS = '/\b(ref|reference|référence|invoice|facture|document|doc|no|number|num|numéro|date|amount|montant|total|debit|débit|credit|crédit|type|description|libellé|details|balance|solde|supplier|fournisseur|vendor|posting|due)\b/iu';

    /**
     * @return int 0-based index of the header row
     */
    public function detect(RawTable $table): int
    {
        $best = 0;
        $bestScore = PHP_INT_MIN;

        foreach (array_slice($table->rows, 0, self::SCAN_ROWS, true) as $index => $row) {
            $score = $this->score($row, $table->rows[$index + 1] ?? []);

            if ($score > $bestScore) {
                $best = $index;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @param  list<string>  $row
     * @param  list<string>  $next
     */
    private function score(array $row, array $next): int
    {
        $cells = array_values(array_filter(array_map('trim', $row), fn (string $c): bool => $c !== ''));

        if (count($cells) < 2) {
            return PHP_INT_MIN + 1;
        }

        $score = count($cells);

        foreach ($cells as $cell) {
            if (preg_match('/\d/', $cell) === 1) {
                $score -= 2;
            }

            if (preg_match(self::KEYWORDS, $cell) === 1) {
                $score += 3;
            }
        }

        // A header is followed by data containing digits.
        $nextWithDigits = count(array_filter($next, fn (string $c): bool => preg_match('/\d/', $c) === 1));

        return $score + min($nextWithDigits, 3);
    }
}
