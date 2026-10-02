<?php

namespace App\Tools\SupplierReconciliation\Interest;

use Illuminate\Console\Command;

/**
 * Aggregated answers to "Do this every month?" — the signal for building the
 * first paid product (spec §50). Emails are counted, never printed.
 */
class InterestReport extends Command
{
    protected $signature = 'supplier-reconciliation:interest {--days=30 : Only answers of the last N days}';

    protected $description = 'Summarise the interest in saving suppliers and mappings (paid product signal)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $answers = InterestResponse::query()->where('created_at', '>=', now()->subDays($days))->get();

        $this->info("Answers in the last {$days} day(s): {$answers->count()}");
        $this->line('With an email: '.$answers->whereNotNull('email')->count());

        foreach ([
            'suppliers_per_month' => InterestResponse::SUPPLIERS_PER_MONTH,
            'accounting_software' => InterestResponse::ACCOUNTING_SOFTWARE,
            'price_answer' => InterestResponse::PRICE_ANSWERS,
        ] as $field => $labels) {
            $counts = $answers->countBy($field);

            $this->newLine();
            $this->table(
                [str_replace('_', ' ', ucfirst($field)), 'Answers'],
                array_map(fn (string $value, string $label): array => [$label, $counts->get($value, 0)], array_keys($labels), $labels),
            );
        }

        $wanted = $answers->pluck('wanted_next')->flatten()->countBy();

        $this->newLine();
        $this->table(
            ['Asked for next (scope C)', 'Answers'],
            array_map(fn (string $value, string $label): array => [$label, $wanted->get($value, 0)], array_keys(InterestResponse::WANTED_NEXT), InterestResponse::WANTED_NEXT),
        );

        $this->newLine();
        $this->line('Prices shown: '.($answers->pluck('price_shown')->unique()->implode(', ') ?: '—'));
        $this->line('Clicks on "Save this supplier" are logged as supplier-reconciliation.save_supplier_clicked.');

        return self::SUCCESS;
    }
}
