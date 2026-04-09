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
        $table->foreignId('address_id')->after('user_id')->constrained('addresses');
        $table->text('note')->nullable()->after('address_id');
    });
}

public function down(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->dropForeign(['address_id']);
        $table->dropColumn(['address_id', 'note']);
    });
}
};
