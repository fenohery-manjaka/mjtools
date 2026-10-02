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
        Schema::table('supplier_reconciliation_interest', function (Blueprint $table) {
            // Later capabilities asked for (PDF, batch, integration...), measured separately (spec §49).
            $table->json('wanted_next')->nullable()->after('price_answer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_reconciliation_interest', function (Blueprint $table) {
            $table->dropColumn('wanted_next');
        });
    }
};
