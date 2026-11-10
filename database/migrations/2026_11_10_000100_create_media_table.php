<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المنصة تخزّن ملفاً لأول مرة (plan 2026-11-09 البند 2). Until now every asset was Markdown in `tool_outputs`, so
 * this is greenfield: there is nothing to migrate, only a contract to get right from the start.
 *
 * The row is the file's only record. Without it a generated image is an orphan on a remote disk that nothing can
 * find, bill or clean up — which is how object storage quietly becomes the most expensive part of a free platform.
 * `checksum` makes a re-render deduplicable; `tool_output_id` is what «referenced» means, and an unreferenced file
 * is what `media:prune` deletes after its grace period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Null means the file is not referenced by any saved asset yet — the prune window starts from created_at.
            $table->foreignId('tool_output_id')->nullable()->constrained('tool_outputs')->nullOnDelete();
            $table->string('disk', 32)->default('assets');
            $table->string('path', 512);
            $table->string('kind', 32)->default('image');
            $table->string('purpose', 64)->default('asset');
            $table->string('mime', 128);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('original_name')->nullable();
            // Public (served from the CDN domain) or private (temporary signed links only).
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->unique(['disk', 'path']);
            $table->index(['workspace_id', 'created_at']);
            $table->index(['tool_output_id']);
            $table->index(['checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
