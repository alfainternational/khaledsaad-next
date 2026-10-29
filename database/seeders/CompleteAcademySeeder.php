<?php

namespace Database\Seeders;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\ResourceTemplate;
use App\Domain\Lab\Models\Tool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Additive curriculum release: never rerun the destructive foundation seed here. */
class CompleteAcademySeeder extends Seeder
{
    public function run(): void
    {
        $tool = Tool::query()->where('slug', 'lesson-lab')->firstOrFail();
        DB::transaction(function () use ($tool): void {
            foreach (CompleteAcademyContent::courses() as $slug => $definition) {
                $course = Course::query()->where('slug', $slug)->firstOrFail();
                $module = $course->modules()->firstOrCreate(['slug' => 'applied-curriculum'], [
                    'title' => $definition['module'], 'outcome' => $course->outcome, 'position' => 1,
                ]);
                $created = false;
                $lessons = [];
                foreach ($definition['lessons'] as $index => $data) {
                    $template = ResourceTemplate::query()->firstOrCreate(['slug' => $data['slug'].'-template'], [
                        'name' => $data['outcome_title'], 'description' => $data['objective'], 'template' => $data['guided_steps'],
                        'format' => 'markdown', 'body_markdown' => preg_replace('/\{\{[^}]+\}\}/u', '________________', $data['lab']['template']), 'is_published' => true,
                    ]);
                    $lesson = $course->lessons()->firstOrCreate(['slug' => $data['slug']], [
                        'course_module_id' => $module->id, 'title' => $data['title'], 'objective' => $data['objective'],
                        'content' => $data['quick_summary'], 'position' => $index + 1, 'duration_minutes' => 25, 'is_preview' => $index === 0,
                        'goal' => $data['objective'], 'concept_body' => $data['concept'], 'common_mistake' => $data['common_mistake'],
                        'market_example' => $data['market_example'], 'quick_summary' => $data['quick_summary'],
                        'scenario_template' => $data['scenario_template'], 'general_scenario' => $data['general_scenario'],
                        'guided_steps' => $data['guided_steps'], 'lab_tool_id' => $tool->id, 'lab_config' => $data['lab'],
                        'whatif_rules' => $data['whatif'], 'template_id' => $template->id, 'outcome_type' => $data['outcome_type'],
                        'outcome_title' => $data['outcome_title'], 'real_action_template' => $data['real_action'],
                        'real_result_fields' => $data['real_result_fields'], 'published_at' => '2026-10-28 00:00:00',
                    ]);
                    $newLesson = $lesson->wasRecentlyCreated;
                    $created = $created || $newLesson;
                    if ($template->wasRecentlyCreated) {
                        $template->update(['lesson_id' => $lesson->id]);
                    }
                    // Existing records belong to the editor, including their question IDs and links.
                    if ($newLesson) {
                        $activity = $lesson->activities()->create(['position' => 1, 'title' => $data['outcome_title'], 'kind' => 'deliverable', 'instructions' => $data['real_action'], 'is_required' => true]);
                        foreach ([$data['opening'], ...$data['questions']] as $position => $question) {
                            $lesson->questions()->create($question + ['activity_id' => $activity->id, 'position' => $position, 'kind' => 'decision', 'is_opening' => $position === 0]);
                        }
                        foreach ($data['needs'] as $type => $label) {
                            $lesson->assetLinks()->create(['required_tool_output_type' => $type, 'label' => $label]);
                        }
                    }
                    $lessons[] = [$lesson, $newLesson];
                }
                foreach ($lessons as $index => [$lesson, $newLesson]) {
                    if ($newLesson && isset($lessons[$index + 1])) {
                        $lesson->update(['next_lesson_id' => $lessons[$index + 1][0]->id]);
                    }
                }
                // A subsequent editor unpublish is deliberate and survives reseeding.
                if ($created) {
                    $course->update(['is_published' => true, 'duration_minutes' => 150]);
                }
            }
        });
    }
}
