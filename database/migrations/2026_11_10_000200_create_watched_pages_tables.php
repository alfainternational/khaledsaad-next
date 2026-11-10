<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مراقب الصفحات (spec 2026-11-09 §5، الموجة 2) — «هنا يولد الاشتراك المتكرّر» بنصّ المواصفة.
 *
 * Every other tool here answers a question once. This one answers it again every day, which is the difference
 * between a tool someone buys once and a service they keep paying for: a competitor's price change, a new offer,
 * a changed promise — the things a small business finds out three months late, if at all.
 *
 * Two columns carry the design:
 *   - `checksum` is of NORMALISED text, not of the page. A page whose footer prints today's date changes every
 *     single day, and a watcher that cries wolf daily is deleted within a week.
 *   - `baseline_text` keeps what was seen last, because a change is only useful as a DIFF. Without it the member
 *     is told that something changed and left to find out what — the least valuable half of the feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watched_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 140);
            $table->string('url', 2000);
            // Hash of the url so uniqueness survives a 2000-character column on MySQL's index limits.
            $table->string('url_hash', 64);
            $table->string('checksum', 64)->nullable();
            $table->longText('baseline_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('notify')->default(true);
            $table->unsignedSmallInteger('change_count')->default(0);
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->string('last_error', 200)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'url_hash']);
            $table->index(['is_active', 'last_checked_at']);
        });

        Schema::create('page_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watched_page_id')->constrained()->cascadeOnDelete();
            $table->string('checksum', 64);
            $table->unsignedSmallInteger('added_lines')->default(0);
            $table->unsignedSmallInteger('removed_lines')->default(0);
            $table->json('added')->nullable();
            $table->json('removed')->nullable();
            // Written by a model when one is configured; the diff above stands on its own without it.
            $table->text('summary')->nullable();
            $table->boolean('seen')->default(false);
            $table->timestamps();

            $table->index(['watched_page_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_changes');
        Schema::dropIfExists('watched_pages');
    }
};
