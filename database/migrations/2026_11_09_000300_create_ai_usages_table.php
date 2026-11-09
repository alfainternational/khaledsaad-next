<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every AI call leaves one row here. The gateway returned token counts from the first day and nothing stored them,
 * which made a spending cap impossible to build; this table is what the monthly workspace quota is measured on.
 *
 * `operation_key` is unique and written with insertOrIgnore, the same idempotency contract as credit_entries, so a
 * retried run cannot double-count its own allowance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('run_id')->nullable();
            $table->string('operation_key')->unique();
            $table->string('provider', 64);
            $table->string('model', 128);
            $table->string('capability', 32)->default('text');
            $table->string('purpose', 128)->default('unknown');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            // Zero while the chain is free; the column is what makes the paid day a config change, not a migration.
            $table->unsignedBigInteger('cost_micro_usd')->default(0);
            $table->boolean('cached')->default(false);
            $table->boolean('sensitive')->default(false);
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
