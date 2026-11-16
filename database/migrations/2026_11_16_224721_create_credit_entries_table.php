<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_account_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('generation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference', 100);
            $table->string('type');
            $table->string('amount', 64);
            $table->string('reserved_amount', 64);
            $table->string('balance_after', 64);
            $table->string('reserved_after', 64);
            $table->timestamps();
            $table->unique(['credit_account_id', 'reference']);
            $table->unique(['generation_id', 'type']);
            $table->index(['credit_account_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_entries');
    }
};
