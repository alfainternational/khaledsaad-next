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

        Schema::create('learning_paths', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary');
            $table->string('audience');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
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

        Schema::create('learning_path_course', function (Blueprint $table): void {
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->unique(['learning_path_id', 'course_id']);
        });

        Schema::create('course_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('outcome');
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
            $table->unique(['course_id', 'slug']);
        });

        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_module_id')->nullable()->constrained()->nullOnDelete();
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

        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('kind')->default('deliverable');
            $table->text('instructions');
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->string('kind')->default('text');
            $table->json('options')->nullable();
            $table->json('answer_key')->nullable();
            $table->timestamps();
        });

        Schema::create('attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status')->default('submitted');
            $table->json('answers')->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'activity_id', 'attempt_number']);
        });

        Schema::create('activity_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->json('deliverable')->nullable();
            $table->unique(['user_id', 'lesson_id']);
            $table->unique(['user_id', 'activity_id']);
        });

        Schema::create('resource_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->json('template');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
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

        Schema::create('tool_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('input_schema');
            $table->json('output_schema')->nullable();
            $table->string('prompt_key')->nullable();
            $table->string('config_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tool_id', 'version']);
        });

        Schema::create('tool_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('status')->default('queued');
            $table->json('input_payload');
            $table->json('output_payload')->nullable();
            $table->unsignedInteger('reserved_credits')->default(0);
            $table->unsignedInteger('used_credits')->default(0);
            $table->string('error_code')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('tool_outputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tool_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['tool_run_id', 'version']);
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

        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('provider_price_id')->nullable()->unique();
            $table->unsignedInteger('price')->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->unsignedInteger('credits')->default(0);
            $table->json('entitlements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan')->default('free');
            $table->string('status')->default('active');
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable()->unique();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('status')->default('pending');
            $table->string('provider_checkout_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('provider')->default('configured');
            $table->string('provider_payment_id')->unique();
            $table->string('provider_price_id');
            $table->string('plan');
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('status');
            $table->json('payload');
            $table->timestamp('paid_at')->nullable();
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
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('credit_entries');
        Schema::dropIfExists('tool_outputs');
        Schema::dropIfExists('tool_runs');
        Schema::dropIfExists('tool_versions');
        Schema::dropIfExists('tools');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('activity_completions');
        Schema::dropIfExists('attempts');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('resource_templates');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('course_modules');
        Schema::dropIfExists('learning_path_course');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('learning_paths');
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
