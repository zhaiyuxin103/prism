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
        Schema::create('generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('client_request_id');
            $table->string('request_hash', 64);
            $table->json('request_payload');
            $table->string('provider_generation_id')->nullable()->unique();
            $table->string('submission_state')->default('pending');
            $table->string('status')->default('queued');
            $table->string('estimated_provider_cost', 64);
            $table->string('estimated_user_cost', 64);
            $table->json('estimate_snapshot');
            $table->json('provider_rates')->nullable();
            $table->json('usage')->nullable();
            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->timestamp('provider_created_at')->nullable();
            $table->timestamp('provider_started_at')->nullable();
            $table->timestamp('provider_completed_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('submit_attempts')->default(0);
            $table->unsignedInteger('poll_attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('capacity_wait_started_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_request_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'next_attempt_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
