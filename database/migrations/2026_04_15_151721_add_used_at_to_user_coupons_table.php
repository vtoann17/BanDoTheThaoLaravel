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
    Schema::table('user_coupons', function (Blueprint $table) {
        $table->timestamp('used_at')->nullable()->after('claimed_at');
    });
}

public function down(): void
{
    Schema::table('user_coupons', function (Blueprint $table) {
        $table->dropColumn('used_at');
    });
}
};
