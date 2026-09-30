<?php

namespace App\Tools\SupplierReconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tools\SupplierReconciliation\Http\Presenters\RunPresenter;
use App\Tools\SupplierReconciliation\Http\Requests\StoreDecisionRequest;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Review\Decision;
use App\Tools\SupplierReconciliation\Review\DecisionAction;
use App\Tools\SupplierReconciliation\Review\ManualMatchSuggester;
use App\Tools\SupplierReconciliation\Review\Resolution;
use App\Tools\SupplierReconciliation\Review\ReviewApplier;
use App\Tools\SupplierReconciliation\Review\ReviewedItem;
use App\Tools\SupplierReconciliation\Review\ReviewedResult;
use App\Tools\SupplierReconciliation\Runs\ReconciliationRun;
use App\Tools\SupplierReconciliation\Runs\RunAccess;
use App\Tools\SupplierReconciliation\Runs\UsageLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    private const PER_PAGE = 20;

    /** Filters of the exceptions screen (spec §30), plus the secondary views. */
    private const FILTERS = [
        'all' => 'All',
        'possible_match' => 'Possible matches',
        'ambiguous' => 'Ambiguous',
        'missing_in_ledger' => 'Missing in ledger',
        'ledger_only' => 'Ledger only',
        'amount_mismatch' => 'Amount mismatch',
        'duplicate_suspected' => 'Duplicates',
        'review_required' => 'Review required',
        'matched' => 'Matched',
        'excluded' => 'Not transactions',
    ];

    public function __construct(
        private readonly RunAccess $access,
        private readonly RunPresenter $presenter,
    ) {}

    public function index(Request $request, ReconciliationRun $run): Response|RedirectResponse
    {
        $this->access->ensure($request, $run);

        $reviewed = $run->reviewed();

        if ($reviewed === null) {
            return to_route('supplier-reconciliation.check', $run);
        }

        $filter = array_key_exists((string) $request->query('filter'), self::FILTERS) ? (string) $request->query('filter') : 'all';
        $items = array_values(array_filter($reviewed->items, fn (ReviewedItem $item): bool => $this->matches($item, $filter)));
        $lastPage = max(1, (int) ceil(count($items) / self::PER_PAGE));
        $page = min($lastPage, max(1, (int) $request->query('page', '1')));

        return Inertia::render('tools/supplier-reconciliation/Review', [
            'run' => $this->presenter->run($run),
            'filter' => $filter,
            'filters' => $this->filters($reviewed),
            'items' => array_map(
                fn (ReviewedItem $item): array => $this->presenter->item($item, $reviewed),
                array_slice($items, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            ),
            'pagination' => ['page' => $page, 'last_page' => $lastPage, 'total' => count($items)],
            'attentionCount' => count($reviewed->attentionItems()),
            'matchOptions' => Inertia::optional(fn (): array => $this->matchOptions($request, $reviewed)),
        ]);
    }

    public function store(StoreDecisionRequest $request, ReconciliationRun $run): RedirectResponse
    {
        $this->access->ensure($request, $run);

        $result = $run->engineResult();

        if ($result === null) {
            return to_route('supplier-reconciliation.check', $run);
        }

        $decisions = $run->decisionList();
        $nextId = 1 + max([0, ...array_map(fn (Decision $d): int => (int) $d->id, $decisions)]);

        $decision = new Decision(
            id: (string) $nextId,
            action: DecisionAction::from((string) $request->validated('action')),
            itemId: $request->validated('item_id'),
            statementIds: array_values(array_map('strval', (array) $request->validated('statement_ids', []))),
            ledgerIds: array_values(array_map('strval', (array) $request->validated('ledger_ids', []))),
            decidedAt: now()->toIso8601String(),
        );

        $error = (new ReviewApplier)->rejectionReason($result, $decisions, $decision);

        if ($error !== null) {
            return back()->withErrors(['decision' => $error]);
        }

        $run->decisions = array_map(fn (Decision $d): array => $d->toArray(), [...$decisions, $decision]);
        $run->save();

        // A rejected automatic match is a false automatic match found by a user.
        UsageLog::record('decision', $run, [
            'action' => $decision->action->value,
            'engine_status' => $decision->itemId === null ? null : $result->item($decision->itemId)?->status->value,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $decision->action->label().'.']);

        return back();
    }

    /**
     * Undo a decision. Later decisions that depended on it no longer apply and are dropped.
     */
    public function destroy(Request $request, ReconciliationRun $run, string $decision): RedirectResponse
    {
        $this->access->ensure($request, $run);

        $result = $run->engineResult();

        if ($result === null) {
            return to_route('supplier-reconciliation.check', $run);
        }

        $remaining = array_values(array_filter($run->decisionList(), fn (Decision $d): bool => $d->id !== $decision));
        $applied = (new ReviewApplier)->apply($result, $remaining)->appliedDecisions;

        $run->decisions = array_map(fn (Decision $d): array => $d->toArray(), $applied);
        $run->save();

        UsageLog::record('decision_undone', $run);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Decision undone.']);

        return back();
    }

    private function matches(ReviewedItem $item, string $filter): bool
    {
        return match ($filter) {
            'all' => $item->needsAttention(),
            'matched' => $item->status === ItemStatus::Matched,
            'excluded' => $item->resolution === Resolution::Excluded,
            default => $item->needsAttention() && $item->status->value === $filter,
        };
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    private function filters(ReviewedResult $reviewed): array
    {
        $filters = [];

        foreach (self::FILTERS as $key => $label) {
            $filters[] = [
                'key' => $key,
                'label' => $label,
                'count' => count(array_filter($reviewed->items, fn (ReviewedItem $item): bool => $this->matches($item, $key))),
            ];
        }

        return $filters;
    }

    /**
     * Lines that can be linked manually to the item being reviewed.
     *
     * @return array{item_id: string, options: list<array<string, mixed>>}|null
     */
    private function matchOptions(Request $request, ReviewedResult $reviewed): ?array
    {
        $item = $reviewed->item((string) $request->query('match_for'));

        if ($item === null || ! in_array(ReviewedItem::ACTION_MANUAL_MATCH, $item->actions, true)) {
            return null;
        }

        $options = (new ManualMatchSuggester)->suggest($reviewed, $item, mb_substr((string) $request->query('q', ''), 0, 100));

        return [
            'item_id' => $item->id,
            'options' => array_map(fn ($transaction): array => $this->presenter->transaction($transaction), $options),
        ];
    }
}
