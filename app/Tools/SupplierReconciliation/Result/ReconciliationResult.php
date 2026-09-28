<?php

namespace App\Tools\SupplierReconciliation\Result;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use InvalidArgumentException;

/**
 * Immutable output of the matching engine. Human decisions are never stored
 * here; they are applied on top (see Review).
 */
final readonly class ReconciliationResult
{
    public const ENGINE_VERSION = '1';

    /** @var array<string, Transaction> */
    public array $transactions;

    /** @var array<string, string> transaction id => item id */
    private array $itemIdByTransaction;

    /**
     * @param  list<Transaction>  $transactions
     * @param  list<ResultItem>  $items
     * @param  array<string, int|string>  $policy  Thresholds used, for audit.
     */
    public function __construct(
        array $transactions,
        public array $items,
        public array $policy = [],
    ) {
        $byId = [];

        foreach ($transactions as $transaction) {
            $byId[$transaction->id] = $transaction;
        }

        $this->transactions = $byId;

        $index = [];

        foreach ($items as $item) {
            foreach ($item->transactionIds() as $id) {
                if (isset($index[$id])) {
                    throw new InvalidArgumentException("Transaction [{$id}] belongs to more than one item.");
                }

                $index[$id] = $item->id;
            }
        }

        $this->itemIdByTransaction = $index;
    }

    public function transaction(string $id): Transaction
    {
        return $this->transactions[$id] ?? throw new InvalidArgumentException("Unknown transaction [{$id}].");
    }

    public function item(string $id): ?ResultItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }

        return null;
    }

    public function itemIdFor(string $transactionId): ?string
    {
        return $this->itemIdByTransaction[$transactionId] ?? null;
    }

    /**
     * @return list<Transaction>
     */
    public function transactionsOn(Side $side): array
    {
        return array_values(array_filter($this->transactions, fn (Transaction $t): bool => $t->side === $side));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'engine_version' => self::ENGINE_VERSION,
            'policy' => $this->policy,
            'transactions' => array_values(array_map(fn (Transaction $t): array => $t->toArray(), $this->transactions)),
            'items' => array_map(fn (ResultItem $i): array => $i->toArray(), $this->items),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, int|string> $policy */
        $policy = (array) ($data['policy'] ?? []);

        return new self(
            transactions: array_values(array_map(fn (array $t): Transaction => Transaction::fromArray($t), (array) $data['transactions'])),
            items: array_values(array_map(fn (array $i): ResultItem => ResultItem::fromArray($i), (array) $data['items'])),
            policy: $policy,
        );
    }
}
