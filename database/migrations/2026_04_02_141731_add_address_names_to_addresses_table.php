<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('addresses', function (Blueprint $table) {
        $table->string('province_name')->nullable();
        $table->string('district_name')->nullable();
        $table->string('ward_name')->nullable();
    });
}

public function down()
{
    Schema::table('addresses', function (Blueprint $table) {
        $table->dropColumn([
            'province_name',
            'district_name',
            'ward_name'
        ]);
    });
}
};
