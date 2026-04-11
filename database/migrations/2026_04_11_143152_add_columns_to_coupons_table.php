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
    Schema::table('coupons', function (Blueprint $table) {
        $table->id()->first();                                   
        $table->decimal('max_discount', 10, 2)->nullable();       
        $table->integer('usage_limit')->nullable();               
        $table->integer('used_count')->default(0);                
        $table->datetime('start_date')->nullable();               
        $table->datetime('end_date')->nullable();                 
        $table->boolean('is_active')->default(1);                 
        $table->timestamps();                                    
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            //
        });
    }
};
