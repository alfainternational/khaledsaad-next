<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** learner_nudges (blueprint §6.6): the reminders sent from the learner-success box and what came of them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_nudges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 40)->default('asset_waiting');
            $table->string('channel', 20)->default('email');
            // Delivery result: queued → sent | failed. Behavioural result: returned_at = first lesson activity after the nudge.
            $table->string('status', 20)->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'lesson_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_nudges');
    }
};
