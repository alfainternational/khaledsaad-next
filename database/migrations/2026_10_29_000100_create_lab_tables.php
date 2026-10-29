<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 2 — المختبر التسويقي (المخطط v1.3 §4.7 و§6.4 و§6.6 و§7):
 * الأداة كحلقة في سلسلة أصول (feeds_from/feeds_into)، فحص قابلية التنفيذ (quality_rules)، قالب المخرج،
 * إصدارات الأصل (parent_output_id) ومشاركته برابط (share_token)، الخطة المجمّعة (plan_assemblies)، وتجارب A/B على إصدارات القالب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table): void {
            $table->string('tagline')->nullable()->after('description');
            $table->string('output_type', 60)->nullable()->after('kind');
            $table->json('feeds_from')->nullable()->after('input_schema');
            $table->json('feeds_into')->nullable()->after('feeds_from');
            $table->json('quality_rules')->nullable()->after('feeds_into');
            $table->longText('output_template')->nullable()->after('quality_rules');
            $table->json('example_input')->nullable()->after('output_template');
            $table->string('course_slug', 80)->nullable()->after('example_input');
            $table->unsignedSmallInteger('position')->default(99)->after('course_slug');
        });
        // kind ∈ generator | calculator | simulator (the old deterministic/ai split is now the credit_cost of the AI enhancement).
        DB::table('tools')->whereIn('kind', ['deterministic', 'ai'])->update(['kind' => 'generator']);

        Schema::table('tool_versions', function (Blueprint $table): void {
            $table->string('label')->nullable()->after('version');
            $table->longText('output_template')->nullable()->after('output_schema');
            $table->text('ai_instructions')->nullable()->after('output_template');
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
        });

        Schema::table('tool_outputs', function (Blueprint $table): void {
            $table->foreignId('tool_id')->nullable()->after('tool_run_id')->constrained()->nullOnDelete();
            $table->foreignId('tool_version_id')->nullable()->after('tool_id')->constrained()->nullOnDelete();
            $table->foreignId('parent_output_id')->nullable()->after('tool_version_id')->constrained('tool_outputs')->nullOnDelete();
            $table->string('share_token', 40)->nullable()->unique()->after('quality_report');
            $table->timestamp('shared_at')->nullable()->after('share_token');
            $table->index(['tool_id', 'created_at']);
        });

        Schema::create('plan_assemblies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->json('outputs');
            $table->timestamp('assembled_at');
            $table->timestamps();
            $table->index(['project_id', 'assembled_at']);
        });

        Schema::create('experiments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('metric', 40)->default('save_rate');
            $table->string('status', 20)->default('running');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('winner_variant_id')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'status']);
        });

        Schema::create('experiment_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->string('label', 10);
            $table->foreignId('tool_version_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('weight')->default(50);
            $table->timestamps();
        });

        Schema::create('experiment_exposures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('experiment_variants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_token', 64)->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['experiment_id', 'variant_id']);
            $table->index(['experiment_id', 'session_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiment_exposures');
        Schema::dropIfExists('experiment_variants');
        Schema::dropIfExists('experiments');
        Schema::dropIfExists('plan_assemblies');
        Schema::table('tool_outputs', function (Blueprint $table): void {
            $table->dropIndex(['tool_id', 'created_at']);
            $table->dropUnique(['share_token']);
            $table->dropConstrainedForeignId('tool_id');
            $table->dropConstrainedForeignId('tool_version_id');
            $table->dropConstrainedForeignId('parent_output_id');
            $table->dropColumn(['share_token', 'shared_at']);
        });
        Schema::table('tool_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['label', 'output_template', 'ai_instructions']);
        });
        DB::table('tools')->where('kind', 'generator')->update(['kind' => 'deterministic']);
        Schema::table('tools', function (Blueprint $table): void {
            $table->dropColumn(['tagline', 'output_type', 'feeds_from', 'feeds_into', 'quality_rules', 'output_template', 'example_input', 'course_slug', 'position']);
        });
    }
};
