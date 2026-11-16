<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->foreignId('credit_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reserved_user_cost', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('generations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('credit_account_id');
                $table->dropColumn('reserved_user_cost');
            });
        });
    }
};
