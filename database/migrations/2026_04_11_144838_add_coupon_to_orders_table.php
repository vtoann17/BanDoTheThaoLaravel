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
            $table->string('coupon_code')->nullable()->after('address_id');
            $table->integer('discount')->default(0)->after('shipping_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['coupon_code', 'discount']);
        });
    }
};
