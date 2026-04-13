<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->enum('topic', ['order', 'product', 'payment', 'technical', 'other'])->default('other');
            $table->text('message');
            $table->enum('type', ['form', 'chat'])->default('form');
            $table->enum('status', ['pending', 'in_progress', 'resolved'])->default('pending');
            $table->boolean('is_read')->default(false);
            $table->string('assigned_to')->nullable(); // admin username
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};