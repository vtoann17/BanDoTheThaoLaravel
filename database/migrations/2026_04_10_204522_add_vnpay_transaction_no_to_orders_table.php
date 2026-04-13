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
    Schema::table('orders', function (Blueprint $table) {
        $table->string('vnpay_transaction_no')->nullable()->after('vnpay_txn_ref');
    });
}

public function down(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->dropColumn('vnpay_transaction_no');
    });
}
};
