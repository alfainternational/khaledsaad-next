<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('platform_role')->default('member')->after('password');
        });

        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('active');
            $table->json('profile')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('content_items', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->default('article');
            $table->string('status')->default('draft');
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('eyebrow')->nullable();
            $table->text('excerpt');
            $table->longText('body');
            $table->string('author_name');
            $table->string('reviewer_name')->nullable();
            $table->string('topic')->nullable();
            $table->string('source_mode')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(6);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->string('publisher');
            $table->string('title');
            $table->text('url');
            $table->date('published_on')->nullable();
            $table->string('language', 8)->default('en');
            $table->timestamps();
        });

        Schema::create('content_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['content_item_id', 'version']);
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('level')->default('مبتدئ');
            $table->text('summary');
            $table->unsignedSmallInteger('duration_minutes')->default(90);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('objective');
            $table->longText('content');
            $table->unsignedSmallInteger('position')->default(1);
            $table->unsignedSmallInteger('duration_minutes')->default(20);
            $table->boolean('is_preview')->default(false);
            $table->timestamps();
            $table->unique(['course_id', 'slug']);
        });

        Schema::create('activity_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->json('deliverable')->nullable();
            $table->unique(['user_id', 'lesson_id']);
        });

        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->uuid('verification_code')->unique();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('tools', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category');
            $table->text('description');
            $table->string('kind')->default('deterministic');
            $table->unsignedInteger('credit_cost')->default(0);
            $table->boolean('is_published')->default(false);
            $table->json('input_schema')->nullable();
            $table->timestamps();
        });

        Schema::create('tool_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('status')->default('queued');
            $table->json('input_payload');
            $table->json('output_payload')->nullable();
            $table->unsignedInteger('reserved_credits')->default(0);
            $table->unsignedInteger('used_credits')->default(0);
            $table->string('error_code')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('operation_key')->unique();
            $table->string('type');
            $table->integer('amount');
            $table->string('description');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('plan')->default('free');
            $table->string('status')->default('active');
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable()->unique();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('event_id')->unique();
            $table->string('type');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('context')->nullable();
            $table->uuid('request_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('credit_entries');
        Schema::dropIfExists('tool_runs');
        Schema::dropIfExists('tools');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('activity_completions');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('content_versions');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('workspaces');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('platform_role');
        });
    }
};
