<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعادة القياس بعد 30/60/90 يوماً (spec 2026-11-09 §5، الموجة 2) — الحلقة المغلقة.
 *
 * The platform already measured a member once, told them what to fix, and then never asked again. That is the gap:
 * a diagnosis with no follow-up is advice, and advice is free. A number that moved — or did not — is the only thing
 * that proves a platform worth paying for a second month.
 *
 * `baseline_result_id` is the diagnosis being re-measured and `result_id` the retake that closed it. Keeping both
 * is what makes the delta per dimension computable later, rather than only "you scored 61 today".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baseline_result_id')->constrained('assessment_results')->cascadeOnDelete();
            $table->foreignId('result_id')->nullable()->constrained('assessment_results')->nullOnDelete();
            $table->unsignedSmallInteger('days');
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            // Overall delta, kept on the row so a list of follow-ups needs no second query per item.
            $table->smallInteger('delta')->nullable();
            $table->timestamps();

            $table->unique(['baseline_result_id', 'days']);
            $table->index(['user_id', 'due_at']);
            $table->index(['completed_at', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_followups');
    }
};
