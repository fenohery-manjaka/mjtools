<?php

namespace App\Tools\SupplierReconciliation\Runs;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Import\ImportedTable;
use App\Tools\SupplierReconciliation\Import\RawTable;
use App\Tools\SupplierReconciliation\Mapping\ColumnMapping;
use App\Tools\SupplierReconciliation\Mapping\PreparedSide;
use App\Tools\SupplierReconciliation\Result\ReconciliationResult;
use App\Tools\SupplierReconciliation\Review\Decision;
use App\Tools\SupplierReconciliation\Review\ReviewApplier;
use App\Tools\SupplierReconciliation\Review\ReviewedResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * One free reconciliation: the two imported files (as extracted cells, never
 * the raw files), their mappings, the engine result and human decisions.
 * Deleted automatically after the retention period.
 *
 * @property string $id
 * @property string $owner_token_hash
 * @property array<string, mixed>|null $statement_file
 * @property array<string, mixed>|null $statement_table
 * @property array<string, mixed>|null $statement_mapping
 * @property array<string, mixed>|null $ledger_file
 * @property array<string, mixed>|null $ledger_table
 * @property array<string, mixed>|null $ledger_mapping
 * @property string|null $currency ISO 4217 code confirmed for the whole reconciliation.
 * @property string|null $result Engine result as JSON lines (see ResultCodec), decoded on demand.
 * @property list<array<string, mixed>>|null $decisions
 * @property CarbonImmutable|null $reconciled_at
 * @property CarbonImmutable $expires_at
 */
class ReconciliationRun extends Model
{
    use HasUlids;

    protected $table = 'supplier_reconciliation_runs';

    protected $guarded = [];

    protected $hidden = ['owner_token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statement_file' => 'array',
            'statement_table' => 'array',
            'statement_mapping' => 'array',
            'ledger_file' => 'array',
            'ledger_table' => 'array',
            'ledger_mapping' => 'array',
            'decisions' => 'array',
            'reconciled_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fileInfo(Side $side): ?array
    {
        return $side === Side::Statement ? $this->statement_file : $this->ledger_file;
    }

    public function hasFile(Side $side): bool
    {
        return $this->rawTable($side) !== null;
    }

    public function hasBothFiles(): bool
    {
        return $this->hasFile(Side::Statement) && $this->hasFile(Side::Ledger);
    }

    public function rawTable(Side $side): ?RawTable
    {
        $data = $side === Side::Statement ? $this->statement_table : $this->ledger_table;

        return $data === null ? null : RawTable::fromArray($data);
    }

    public function mapping(Side $side): ?ColumnMapping
    {
        $data = $side === Side::Statement ? $this->statement_mapping : $this->ledger_mapping;

        return $data === null ? null : ColumnMapping::fromArray($data);
    }

    public function importedTable(Side $side): ?ImportedTable
    {
        $raw = $this->rawTable($side);
        $mapping = $this->mapping($side);

        return $raw === null ? null : ImportedTable::fromRaw($raw, $mapping === null ? 0 : $mapping->headerIndex);
    }

    public function prepared(Side $side): ?PreparedSide
    {
        $table = $this->importedTable($side);
        $mapping = $this->mapping($side);

        return $table === null || $mapping === null ? null : PreparedSide::prepare($side, $table, $mapping);
    }

    public function engineResult(): ?ReconciliationResult
    {
        return $this->result === null ? null : (new ResultCodec)->decode($this->result);
    }

    public function storeResult(ReconciliationResult $result): void
    {
        $this->result = (new ResultCodec)->encode($result);
        $this->decisions = [];
        $this->reconciled_at = now()->toImmutable();
    }

    /**
     * @return list<Decision>
     */
    public function decisionList(): array
    {
        return array_map(fn (array $data): Decision => Decision::fromArray($data), $this->decisions ?? []);
    }

    public function reviewed(): ?ReviewedResult
    {
        $result = $this->engineResult();

        return $result === null ? null : (new ReviewApplier)->apply($result, $this->decisionList());
    }

    /**
     * Forget results that no longer reflect the files or mappings.
     */
    public function discardResult(): void
    {
        $this->result = null;
        $this->decisions = null;
        $this->reconciled_at = null;
    }

    public function isReconciled(): bool
    {
        return $this->result !== null;
    }
}
