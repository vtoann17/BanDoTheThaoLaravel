<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('vnpay_txn_ref')->nullable()->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('vnpay_txn_ref');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['vnpay_txn_ref', 'paid_at']);
        });
    }
};
