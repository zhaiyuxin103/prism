<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('balance', 64)->default('0.000000000000000000');
            $table->string('reserved', 64)->default('0.000000000000000000');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_accounts');
    }
};
