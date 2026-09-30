<?php

namespace App\Tools\SupplierReconciliation\Matching\Stages;

use App\Tools\SupplierReconciliation\Matching\ItemFactory;
use App\Tools\SupplierReconciliation\Matching\MatchingContext;
use App\Tools\SupplierReconciliation\Matching\PairEvidence;
use App\Tools\SupplierReconciliation\Matching\ReferenceRelation;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\Reason;
use App\Tools\SupplierReconciliation\Result\ResultItem;

/**
 * Proposals that need a human decision (spec §18, §19). Same amount is always
 * required. Candidates are ranked in classes:
 *
 *   A. related reference (any level);
 *   B. no usable reference on one side, dates close;
 *   C. different references, dates very close (only proposed when unique).
 *
 * Each line keeps only its best class. Pairs kept by both lines form groups:
 * one line on each side gives a Possible Match, anything larger is Ambiguous
 * — the engine never picks one candidate arbitrarily.
 */
final class CandidateStage implements MatchingStage
{
    private const CLASS_RELATED_REFERENCE = 1;

    private const CLASS_NO_REFERENCE = 2;

    private const CLASS_DIFFERENT_REFERENCE = 3;

    public function apply(MatchingContext $context): void
    {
        /** @var array<string, int> $classes */
        $classes = [];
        /** @var array<string, int> $best */
        $best = [];
        $edges = [];

        foreach ($context->openEdges() as $edge) {
            $class = $this->classify($context, $edge);

            if ($class === null) {
                continue;
            }

            $edges[] = $edge;
            $classes[$edge->key()] = $class;

            foreach ([$edge->statement->id, $edge->ledger->id] as $id) {
                $best[$id] = min($best[$id] ?? PHP_INT_MAX, $class);
            }
        }

        $mutual = array_values(array_filter(
            $edges,
            fn (PairEvidence $e): bool => $classes[$e->key()] === $best[$e->statement->id]
                && $classes[$e->key()] === $best[$e->ledger->id],
        ));

        foreach ($this->components($mutual) as $component) {
            $class = $classes[$component[0]->key()];
            $item = $this->itemFor($context, $component, $class);

            if ($item !== null) {
                $context->resolve($item);
            }
        }
    }

    private function classify(MatchingContext $context, PairEvidence $edge): ?int
    {
        if (! $edge->amountsEqual()) {
            return null;
        }

        return match (true) {
            $edge->reference->isRelated() => self::CLASS_RELATED_REFERENCE,
            $edge->reference === ReferenceRelation::Unavailable
                && $edge->datesWithin($context->policy->amountOnlyMaxDateDays) => self::CLASS_NO_REFERENCE,
            $edge->reference === ReferenceRelation::Different
                && $edge->datesWithin($context->policy->differentReferenceMaxDateDays) => self::CLASS_DIFFERENT_REFERENCE,
            default => null,
        };
    }

    /**
     * Connected groups of pairs, in a deterministic order.
     *
     * @param  list<PairEvidence>  $edges
     * @return list<list<PairEvidence>>
     */
    private function components(array $edges): array
    {
        $parent = [];

        $find = function (string $id) use (&$parent, &$find): string {
            if (($parent[$id] ?? $id) === $id) {
                return $id;
            }

            return $parent[$id] = $find($parent[$id]);
        };

        foreach ($edges as $edge) {
            $a = $find($edge->statement->id);
            $b = $find($edge->ledger->id);

            if ($a !== $b) {
                $parent[$b] = $a;
            }
        }

        $components = [];

        foreach ($edges as $edge) {
            $components[$find($edge->statement->id)][] = $edge;
        }

        return array_values($components);
    }

    /**
     * @param  list<PairEvidence>  $edges
     */
    private function itemFor(MatchingContext $context, array $edges, int $class): ?ResultItem
    {
        if (count($edges) === 1) {
            return ItemFactory::pair(
                status: ItemStatus::PossibleMatch,
                kind: null,
                headline: 'Possible match',
                evidence: $edges[0],
                extraReasons: [$this->whyNotAutomatic($context, $edges[0], $class)],
            );
        }

        if ($class === self::CLASS_DIFFERENT_REFERENCE) {
            // Too weak to present several interpretations: the lines stay unmatched.
            return null;
        }

        usort($edges, fn (PairEvidence $a, PairEvidence $b): int => $b->score() <=> $a->score());

        $statementIds = [];
        $ledgerIds = [];

        foreach ($edges as $edge) {
            $statementIds[$edge->statement->id] = $edge->statement->rowNumber;
            $ledgerIds[$edge->ledger->id] = $edge->ledger->rowNumber;
        }

        asort($statementIds);
        asort($ledgerIds);

        return new ResultItem(
            id: '',
            status: ItemStatus::Ambiguous,
            kind: null,
            headline: $this->ambiguousHeadline(count($statementIds), count($ledgerIds)),
            statementIds: array_keys($statementIds),
            ledgerIds: array_keys($ledgerIds),
            reasons: [
                Reason::differs('ambiguous.several_candidates', 'Several pairings are equally plausible ('.count($statementIds).' statement and '.count($ledgerIds).' ledger lines with the same amount).'),
                Reason::info('ambiguous.basis', $class === self::CLASS_RELATED_REFERENCE
                    ? 'Candidates have related references and the same amount.'
                    : 'Candidates have the same amount and close dates, without a usable reference.'),
                Reason::info('ambiguous.no_choice', 'The engine does not pick one arbitrarily: choose the right pairing or leave it for review.'),
            ],
            candidates: array_map(fn (PairEvidence $e) => $e->toCandidate(), $edges),
        );
    }

    private function whyNotAutomatic(MatchingContext $context, PairEvidence $edge, int $class): Reason
    {
        if ($class === self::CLASS_NO_REFERENCE) {
            return Reason::partial('possible.no_reference', 'No usable reference on one side: only the amount and the date agree. Please confirm.');
        }

        if ($class === self::CLASS_DIFFERENT_REFERENCE) {
            return Reason::partial('possible.different_reference', 'The references differ: only the amount and the date agree. Please confirm.');
        }

        if (! $edge->reference->isStrong()) {
            return Reason::partial('possible.transformed_reference', 'The reference only matches after a significant transformation ('.lcfirst($edge->reference->label()).'): needs your confirmation.');
        }

        $tolerance = $edge->reference === ReferenceRelation::LeadingZeros
            ? $context->policy->leadingZerosMaxDateDays
            : $context->policy->certainMaxDateDays;

        return match (true) {
            $edge->dateDifference !== null && $edge->dateDifference > $tolerance => Reason::partial('possible.date_gap', "Dates are {$edge->dateDifference} days apart, more than the {$tolerance}-day tolerance for automatic matches."),
            $edge->dateDifference === null && $edge->reference === ReferenceRelation::LeadingZeros => Reason::partial('possible.no_date', 'Leading zeros had to be removed and the dates cannot be compared: needs your confirmation.'),
            $edge->dateDifference === null => Reason::partial('possible.unreadable_date', 'A date could not be read, so the match could not be confirmed automatically.'),
            default => Reason::partial('possible.competition', 'Another line with the same amount competed for this match: please confirm.'),
        };
    }

    private function ambiguousHeadline(int $statementCount, int $ledgerCount): string
    {
        return match (true) {
            $statementCount === 1 => "{$ledgerCount} possible ledger matches",
            $ledgerCount === 1 => "{$statementCount} statement lines could match one ledger line",
            default => "{$statementCount} statement and {$ledgerCount} ledger lines could match each other",
        };
    }
}
