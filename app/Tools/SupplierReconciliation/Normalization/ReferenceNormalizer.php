<?php

namespace App\Tools\SupplierReconciliation\Normalization;

use App\Tools\SupplierReconciliation\Domain\NormalizedReference;

/**
 * Produces comparison forms of a reference. Only typographic differences are
 * removed here; dropping zeros or prefixes is a weaker relation decided by the
 * matching engine, never silently applied.
 */
final class ReferenceNormalizer
{
    /** Minimum length of the typographic key for a reference to identify a document. */
    private const MIN_IDENTIFYING_LENGTH = 3;

    public function normalize(?string $raw): ?NormalizedReference
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim(preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $raw) ?? $raw);
        $upper = mb_strtoupper($trimmed);
        $typographicKey = preg_replace('/[^\p{L}0-9]+/u', '', $upper) ?? '';

        if ($typographicKey === '') {
            return null;
        }

        preg_match_all('/\p{L}+|[0-9]+/u', $upper, $matches);
        $runs = $matches[0];

        $letterRuns = [];
        $digitRuns = [];
        $zeroRuns = [];

        foreach ($runs as $run) {
            if (ctype_digit($run)) {
                $stripped = ltrim($run, '0');
                $stripped = $stripped === '' ? '0' : $stripped;
                $digitRuns[] = $stripped;
                $zeroRuns[] = $stripped;
            } else {
                $letterRuns[] = $run;
                $zeroRuns[] = $run;
            }
        }

        $significantDigits = array_sum(array_map(
            fn (string $run): int => $run === '0' ? 0 : strlen($run),
            $digitRuns,
        ));

        return new NormalizedReference(
            original: $raw,
            typographicKey: $typographicKey,
            zeroKey: implode('|', $zeroRuns),
            letters: implode('|', $letterRuns),
            digitCore: implode('|', $digitRuns),
            significantDigits: $significantDigits,
            identifying: $digitRuns !== [] && mb_strlen($typographicKey) >= self::MIN_IDENTIFYING_LENGTH,
            steps: $this->describeSteps($raw, $trimmed, $upper, $typographicKey),
        );
    }

    /**
     * @return list<string>
     */
    private function describeSteps(string $raw, string $trimmed, string $upper, string $key): array
    {
        $steps = [];

        if ($trimmed !== $raw) {
            $steps[] = 'surrounding or repeated spaces ignored';
        }

        if ($upper !== $trimmed) {
            $steps[] = 'letter case ignored';
        }

        if ($key !== $upper) {
            $steps[] = 'spaces, separators and punctuation ignored';
        }

        return $steps;
    }
}
