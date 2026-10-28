<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 1 — الشريحة العمودية (المخطط v1.3 §7 و§6.6):
 * التشخيص، الشكل الكامل للدرس، مذكرة القرارات، النتائج الواقعية، تراكم الأصول، البلاغات، أحداث المتعلمين، إشارات الجودة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('audience', 20)->nullable()->after('platform_role');
        });

        Schema::table('learning_paths', function (Blueprint $table): void {
            $table->unsignedSmallInteger('position')->default(1)->after('audience');
            $table->string('outcome')->nullable()->after('position');
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->unsignedSmallInteger('position')->default(1)->after('slug');
            $table->string('outcome')->nullable()->after('summary');
            $table->string('topic')->nullable()->after('outcome');
            $table->unsignedBigInteger('prerequisite_course_id')->nullable()->after('topic');
            $table->unsignedBigInteger('next_course_id')->nullable()->after('prerequisite_course_id');
        });

        Schema::table('lessons', function (Blueprint $table): void {
            $table->text('goal')->nullable();
            $table->longText('concept_body')->nullable();
            $table->text('common_mistake')->nullable();
            $table->text('market_example')->nullable();
            $table->text('quick_summary')->nullable();
            $table->text('scenario_template')->nullable();
            $table->json('general_scenario')->nullable();
            $table->json('guided_steps')->nullable();
            $table->foreignId('lab_tool_id')->nullable()->constrained('tools')->nullOnDelete();
            $table->json('lab_config')->nullable();
            $table->json('whatif_rules')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('resource_templates')->nullOnDelete();
            $table->unsignedBigInteger('next_lesson_id')->nullable();
            $table->string('outcome_type', 60)->nullable();
            $table->string('outcome_title')->nullable();
            $table->text('real_action_template')->nullable();
            $table->json('real_result_fields')->nullable();
            $table->timestamp('published_at')->nullable();
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->foreignId('lesson_id')->nullable()->after('activity_id')->constrained()->cascadeOnDelete();
            $table->text('scenario')->nullable()->after('prompt');
            $table->boolean('is_opening')->default(false)->after('answer_key');
            $table->unsignedSmallInteger('position')->default(1)->after('is_opening');
        });

        Schema::table('resource_templates', function (Blueprint $table): void {
            $table->string('format', 20)->default('markdown')->after('template');
            $table->longText('body_markdown')->nullable()->after('format');
            $table->foreignId('lesson_id')->nullable()->after('body_markdown')->constrained()->nullOnDelete();
            $table->foreignId('tool_id')->nullable()->after('lesson_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('downloads_count')->default(0)->after('tool_id');
        });

        Schema::table('tool_outputs', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('version');
            $table->string('format', 20)->default('markdown')->after('title');
            $table->string('output_type', 60)->nullable()->after('format');
            $table->longText('body_markdown')->nullable()->after('output_type');
            $table->foreignId('user_id')->nullable()->after('body_markdown')->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->json('quality_report')->nullable()->after('lesson_id');
            $table->index(['user_id', 'created_at']);
            $table->index(['project_id', 'output_type']);
        });

        Schema::create('assessment_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('audience', 20);
            $table->string('dimension', 30);
            $table->text('prompt');
            $table->json('options');
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['audience', 'is_active', 'position']);
        });

        Schema::create('assessment_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audience', 20);
            $table->json('project_snapshot');
            $table->json('answers')->nullable();
            $table->json('scores');
            $table->unsignedTinyInteger('overall')->default(0);
            $table->string('level', 20);
            $table->string('weakest_dimension', 30);
            $table->json('priorities');
            $table->foreignId('start_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->string('benchmark_group', 60)->nullable();
            $table->string('session_token', 64)->unique();
            $table->timestamps();
            $table->index(['audience', 'benchmark_group']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('assessment_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('audience', 20);
            $table->string('level', 20);
            $table->string('weakest_dimension', 30);
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->json('priorities');
            $table->timestamps();
            $table->unique(['audience', 'level', 'weakest_dimension']);
        });

        Schema::create('decision_journal', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('first_choice')->nullable();
            $table->unsignedTinyInteger('final_choice')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
        });

        Schema::create('real_world_checkins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 20)->default('commit');
            $table->text('action');
            $table->timestamp('due_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'lesson_id']);
            $table->index(['due_at', 'reminder_sent_at']);
        });

        Schema::create('lesson_asset_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('required_tool_output_type', 60);
            $table->string('label');
            $table->timestamps();
            $table->unique(['lesson_id', 'required_tool_output_type']);
        });

        Schema::create('content_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('part', 20)->nullable();
            $table->string('reason', 60);
            $table->text('note')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('learner_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_token', 64)->nullable();
            $table->string('event', 60);
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('part', 20)->nullable();
            $table->foreignId('tool_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at');
            $table->index(['event', 'created_at']);
            $table->index(['lesson_id', 'event']);
            $table->index(['session_token', 'lesson_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('quality_signals', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 40);
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->string('part', 20)->nullable();
            $table->decimal('value', 8, 2)->default(0);
            $table->unsignedSmallInteger('impact')->default(0);
            $table->string('status', 20)->default('open');
            $table->text('summary');
            $table->json('meta')->nullable();
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->index(['status', 'impact']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('lesson_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['lesson_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_versions');
        Schema::dropIfExists('quality_signals');
        Schema::dropIfExists('learner_events');
        Schema::dropIfExists('content_reports');
        Schema::dropIfExists('lesson_asset_links');
        Schema::dropIfExists('real_world_checkins');
        Schema::dropIfExists('decision_journal');
        Schema::dropIfExists('assessment_rules');
        Schema::dropIfExists('assessment_results');
        Schema::dropIfExists('assessment_questions');
        Schema::table('tool_outputs', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['project_id', 'output_type']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('lesson_id');
            $table->dropColumn(['title', 'format', 'output_type', 'body_markdown', 'quality_report']);
        });
        Schema::table('resource_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lesson_id');
            $table->dropConstrainedForeignId('tool_id');
            $table->dropColumn(['format', 'body_markdown', 'downloads_count']);
        });
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lesson_id');
            $table->dropColumn(['scenario', 'is_opening', 'position']);
        });
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lab_tool_id');
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn(['goal', 'concept_body', 'common_mistake', 'market_example', 'quick_summary', 'scenario_template', 'general_scenario', 'guided_steps', 'lab_config', 'whatif_rules', 'next_lesson_id', 'outcome_type', 'outcome_title', 'real_action_template', 'real_result_fields', 'published_at']);
        });
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn(['position', 'outcome', 'topic', 'prerequisite_course_id', 'next_course_id']);
        });
        Schema::table('learning_paths', function (Blueprint $table): void {
            $table->dropColumn(['position', 'outcome']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('audience');
        });
    }
};
