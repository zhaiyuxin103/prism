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
        Schema::create('generation_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('generation_id')->unique()->constrained()->restrictOnDelete();
            $table->string('outcome');
            $table->string('currency', 3)->default('USD');
            $table->string('provider_cost', 64);
            $table->string('user_charge', 64);
            $table->unsignedInteger('markup_basis_points')->default(2500);
            $table->string('calculation_version')->default('v2');
            $table->timestamp('settled_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generation_settlements');
    }
};
