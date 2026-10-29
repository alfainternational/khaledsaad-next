<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 3 — المجلة ومحرك المحتوى (blueprint §4.10–4.12, §6.3, §6.6, §7).
 * Every added column on content_items is nullable or has a derived default so the rows already in production stay valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });

        Schema::create('content_tag', function (Blueprint $table): void {
            $table->foreignId('content_item_id')->constrained('content_items')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['content_item_id', 'tag_id']);
        });

        Schema::create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->json('links')->nullable();
            $table->string('role', 120)->nullable();
            $table->timestamps();
        });

        Schema::table('content_items', function (Blueprint $table): void {
            $table->string('section', 20)->default('articles')->after('type')->index();
            $table->foreignId('category_id')->nullable()->after('section')->constrained('content_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->after('category_id')->constrained('authors')->nullOnDelete();
            $table->text('tldr')->nullable()->after('excerpt');
            $table->foreignId('related_lesson_id')->nullable()->after('topic')->constrained('lessons')->nullOnDelete();
            $table->foreignId('related_tool_id')->nullable()->after('related_lesson_id')->constrained('tools')->nullOnDelete();
            $table->string('translation_stage', 20)->nullable()->after('source_mode');
            $table->timestamp('last_reviewed_at')->nullable()->after('reviewed_at');
            $table->json('faq')->nullable()->after('meta');
        });

        // Existing rows: section derived from type, translation stage from status, last review from the editorial review.
        foreach (['article' => 'articles', 'guide' => 'articles', 'news' => 'news', 'translated_analysis' => 'insights', 'case_study' => 'case-studies', 'research' => 'research'] as $type => $section) {
            DB::table('content_items')->where('type', $type)->update(['section' => $section]);
        }
        DB::table('content_items')->where('status', 'published')->update(['translation_stage' => 'published']);
        DB::table('content_items')->whereNull('last_reviewed_at')->whereNotNull('reviewed_at')->update(['last_reviewed_at' => DB::raw('reviewed_at')]);

        Schema::create('glossary_terms', function (Blueprint $table): void {
            $table->id();
            $table->string('term', 160);
            $table->string('slug', 120)->unique();
            $table->text('definition');
            $table->text('example')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('related_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->foreignId('related_tool_id')->nullable()->constrained('tools')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
        });

        Schema::create('source_feeds', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('url');
            $table->string('site_url')->nullable();
            $table->string('language', 8)->default('en');
            $table->string('topic', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('last_error')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->timestamps();
        });

        Schema::create('source_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_feed_id')->nullable()->constrained('source_feeds')->nullOnDelete();
            $table->string('title', 300);
            $table->text('url');
            $table->string('url_hash', 64)->unique();
            $table->string('publisher', 160)->nullable();
            $table->string('language', 8)->default('en');
            $table->date('published_on')->nullable();
            $table->text('excerpt')->nullable();
            $table->unsignedTinyInteger('relevance_score')->default(0);
            $table->json('matched_keywords')->nullable();
            // capture → verified → summarized → contextualized → reviewed → published | ignored
            $table->string('stage', 20)->default('captured')->index();
            $table->string('ignored_reason', 160)->nullable();
            $table->json('verification')->nullable();
            $table->longText('summary')->nullable();
            $table->string('summary_mode', 20)->nullable();
            $table->longText('arabic_context')->nullable();
            $table->string('local_example', 600)->nullable();
            $table->foreignId('related_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->foreignId('related_tool_id')->nullable()->constrained('tools')->nullOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained('content_items')->nullOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['stage', 'relevance_score']);
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('source', 40)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE content_items ADD FULLTEXT content_items_search_fulltext (title, excerpt, body)');
            DB::statement('ALTER TABLE glossary_terms ADD FULLTEXT glossary_terms_search_fulltext (term, definition, body)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE content_items DROP INDEX content_items_search_fulltext');
            DB::statement('ALTER TABLE glossary_terms DROP INDEX glossary_terms_search_fulltext');
        }
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('source_items');
        Schema::dropIfExists('source_feeds');
        Schema::dropIfExists('glossary_terms');
        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('author_id');
            $table->dropConstrainedForeignId('related_lesson_id');
            $table->dropConstrainedForeignId('related_tool_id');
            $table->dropColumn(['section', 'tldr', 'translation_stage', 'last_reviewed_at', 'faq']);
        });
        Schema::dropIfExists('authors');
        Schema::dropIfExists('content_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('content_categories');
    }
};
