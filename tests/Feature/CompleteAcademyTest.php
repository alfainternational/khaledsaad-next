<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Services\LessonComposer;
use App\Domain\Academy\Services\WhatIfEngine;
use App\Domain\Lab\Services\AssetVault;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Database\Seeders\CompleteAcademyContent;
use Database\Seeders\CompleteAcademySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompleteAcademyTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_twelve_courses_have_six_complete_lessons_and_working_labs(): void
    {
        $this->seed();
        $this->assertSame(12, Course::where('is_published', true)->count());
        $this->assertSame(72, Lesson::count());
        $composer = app(LessonComposer::class);
        $engine = app(WhatIfEngine::class);
        $slugs = [];
        foreach (CompleteAcademyContent::courses() as $slug => $definition) {
            $course = Course::where('slug', $slug)->firstOrFail();
            $this->assertSame(6, $course->lessons()->count(), $slug);
            $this->get(route('academy.show', $course))->assertOk();
            foreach ($course->lessons as $index => $lesson) {
                $this->assertSame([], array_keys(array_filter($lesson->partCompleteness(), fn ($ok) => ! $ok)), $lesson->slug);
                $words = count(preg_split('/\s+/u', trim(strip_tags($lesson->concept_body))));
                $this->assertGreaterThanOrEqual(300, $words, $lesson->slug.' concept');
                $this->assertLessThanOrEqual(500, $words, $lesson->slug.' concept');
                $this->assertNotContains($lesson->slug, $slugs);
                $slugs[] = $lesson->slug;
                $this->assertSame(4, $lesson->questions()->count());
                foreach ($lesson->questions as $q) {
                    $this->assertCount(3, $q->options);
                    $this->assertSame(1, collect($q->options)->where('is_best', true)->count());
                    foreach ($q->options as $option) {
                        $this->assertNotEmpty($option['feedback']);
                    }
                }
                $input = [];
                foreach ($lesson->guided_steps as $field) {
                    $input[$field['key']] = $field['placeholder'];
                }
                foreach ($lesson->lab_config['fields'] as $field) {
                    $input[$field['key']] = $field['default'];
                }
                $situation = $composer->situation($lesson, null, 'general');
                $values = $composer->values($lesson, $situation, $input);
                $this->assertSame([], $composer->missing($lesson, $values), $lesson->slug);
                $markdown = $composer->renderAsset($lesson, $values);
                $this->assertStringNotContainsString('{{', $markdown, $lesson->slug);
                $this->assertStringContainsString($lesson->outcome_title, $markdown);
                foreach ($input as $key => $value) {
                    if (! in_array($key, ['volume', 'factor', 'target'])) {
                        $this->assertStringContainsString($value, $markdown);
                    }
                }
                $simulation = $engine->evaluate($lesson->whatif_rules, $values, $input['volume'] * 2);
                $this->assertNotEmpty($simulation['outputs']);
                $this->assertNotEquals($values['estimate'], $simulation['outputs'][0]['value']);
                $url = route('academy.lesson', [$course, $lesson]);
                $this->get($url)->assertOk()->assertSee('part-guided', false)->assertSee('part-apply', false);
                $this->post($url.'/lab', $input + ['situation' => 'general'])->assertSessionHasNoErrors();
                $this->assertStringContainsString($lesson->outcome_title, session('lab.asset.'.$lesson->id.'.markdown'));
                if ($index < 5) {
                    $this->assertSame($course->lessons[$index + 1]->id, $lesson->next_lesson_id);
                } else {
                    $this->assertGreaterThanOrEqual(5, $lesson->assetLinks()->count());
                }
            }
        }
    }

    public function test_reseeding_preserves_editor_changes_question_ids_and_member_progress(): void
    {
        $this->seed();
        $lesson = Lesson::where('slug', 'audience-research-question')->firstOrFail();
        $lesson->update(['title' => 'عنوان راجعه المحرر']);
        $lesson->course->update(['is_published' => false]);
        $question = $lesson->questions()->first();
        $question->update(['prompt' => 'سؤال راجعه المحرر']);
        $user = User::factory()->create();
        DB::table('activity_completions')->insert(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        $foundation = Course::where('slug', 'marketing-foundations')->first()->lessons()->pluck('updated_at', 'id')->map(fn ($date) => $date->toISOString())->all();
        $this->seed(CompleteAcademySeeder::class);
        $this->assertSame(72, Lesson::count());
        $this->assertSame('عنوان راجعه المحرر', $lesson->fresh()->title);
        $this->assertFalse($lesson->course->fresh()->is_published);
        $this->assertSame('سؤال راجعه المحرر', $question->fresh()->prompt);
        $this->assertDatabaseHas('activity_completions', ['user_id' => $user->id, 'lesson_id' => $lesson->id]);
        $this->assertSame($foundation, Course::where('slug', 'marketing-foundations')->first()->lessons()->pluck('updated_at', 'id')->map(fn ($date) => $date->toISOString())->all());
    }

    public function test_new_labs_prefill_only_compatible_project_fields_and_allow_overrides(): void
    {
        $this->seed();
        $space = Workspace::create(['name' => 'مساحة', 'slug' => 'prefill']);
        $project = $space->projects()->create(['name' => 'مشروعي', 'slug' => 'mine', 'profile' => ['budget' => 4700, 'audience' => 'جمهور المشروع', 'offer' => 'عرض المشروع']]);
        $composer = app(LessonComposer::class);
        $budget = Lesson::where('slug', 'ads-budget-stop-rules')->first();
        $situation = $composer->situation($budget, $project, 'project');
        $this->assertEquals(4700, $composer->values($budget, $situation, [])['volume']);
        $this->assertEquals(900, $composer->values($budget, $situation, ['volume' => 900])['volume']);
        $this->assertEquals(0, $composer->values($budget, array_replace($situation, ['budget' => 0]), [])['volume']);
        $this->assertEquals(1200, $composer->values($budget, $composer->situation($budget, null, 'general'), [])['volume']);
        $strategy = Lesson::where('slug', 'integrated-strategy-choice')->first();
        $values = $composer->values($strategy, $composer->situation($strategy, $project, 'project'), []);
        $this->assertSame('جمهور المشروع', $values['strategy_segment']);
        $this->assertSame('عرض المشروع', $values['strategy_offer']);
        $unrelated = Lesson::where('slug', 'audience-research-question')->first();
        $this->assertEquals($unrelated->lab_config['fields'][0]['default'], $composer->values($unrelated, $composer->situation($unrelated, $project, 'project'), [])['volume']);
    }

    public function test_final_course_lesson_includes_saved_prior_assets_from_the_selected_project(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'مساحة', 'slug' => 'final-lesson']);
        $workspace->members()->attach($user, ['role' => 'owner']);
        $project = $workspace->projects()->create(['name' => 'مشروعي', 'slug' => 'project']);
        $course = Course::where('slug', 'audience-and-personas')->first();
        foreach ($course->lessons->take(5) as $lesson) {
            app(AssetVault::class)->save($user, $project, null, $lesson->outcome_title, $lesson->outcome_type, '# نسخة قديمة لا تستخدم', [], null, $lesson);
            app(AssetVault::class)->save($user, $project, null, $lesson->outcome_title, $lesson->outcome_type, '# دليل محفوظ '.$lesson->slug, [], null, $lesson);
        }
        $lesson = $course->lessons->last();
        $input = collect($lesson->guided_steps)->mapWithKeys(fn ($field) => [$field['key'] => $field['placeholder']])->all();
        $url = route('academy.lesson', [$course, $lesson]);
        $this->actingAs($user)->post($url.'/lab', $input + ['situation' => 'project'])->assertSessionHasNoErrors();
        $markdown = session('lab.asset.'.$lesson->id.'.markdown');
        $this->assertStringNotContainsString('نسخة قديمة لا تستخدم', $markdown);
        foreach ($course->lessons->take(5) as $previous) {
            $this->assertStringContainsString('دليل محفوظ '.$previous->slug, $markdown);
        }
    }
}
