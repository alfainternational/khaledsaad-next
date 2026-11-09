<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** lesson_variants (blueprint §6.2, §6.6): an alternative training scenario of a lesson for another audience, saved for review. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 60);
            $table->text('scenario');
            $table->string('source', 20)->default('manual');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('applied_version')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['lesson_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_variants');
    }
};
