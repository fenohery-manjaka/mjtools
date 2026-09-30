<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_reconciliation_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('owner_token_hash', 64);
            $table->json('statement_file')->nullable();
            $table->json('statement_table')->nullable();
            $table->json('statement_mapping')->nullable();
            $table->json('ledger_file')->nullable();
            $table->json('ledger_table')->nullable();
            $table->json('ledger_mapping')->nullable();
            $table->longText('result')->nullable();
            $table->json('decisions')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_reconciliation_runs');
    }
};
