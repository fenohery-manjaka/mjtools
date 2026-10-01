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
        // Answers to "Do this every month?" (spec §43). Never linked to a run or to accounting data.
        Schema::create('supplier_reconciliation_interest', function (Blueprint $table) {
            $table->id();
            $table->string('suppliers_per_month', 20);
            $table->string('accounting_software', 30);
            $table->string('accounting_software_other', 100)->nullable();
            $table->string('price_answer', 20);
            $table->string('price_shown', 50);
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_reconciliation_interest');
    }
};
