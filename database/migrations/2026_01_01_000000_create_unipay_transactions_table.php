<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('unipay.logging.table_name', 'unipay_transactions');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 32)->index();
            $table->string('payment_id')->nullable()->index();
            $table->string('transaction_id')->nullable()->index();
            $table->string('invoice_id')->index();
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->string('currency', 3)->default('BDT');
            $table->string('status', 32)->default('pending')->index();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tableName = config('unipay.logging.table_name', 'unipay_transactions');
        Schema::dropIfExists($tableName);
    }
};
