<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Assessment\Models\AssessmentQuestion;
use App\Domain\Assessment\Models\AssessmentResult;
use App\Domain\Assessment\Models\AssessmentRule;
use App\Domain\Assessment\Services\Catalog;
use App\Domain\Workspace\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeAndAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_home_has_try_it_now_form_two_programs_and_hides_numbers_below_ten(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('جرّب على مشروعك الآن')
            ->assertSee('أعطني أول خطوة')
            ->assertSee('أصحاب المشاريع')
            ->assertSee('المسوّقون والوكالات')
            ->assertSee('قِس')
            ->assertDontSee('أصل أُنشئ هذا الأسبوع')
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('data-rules=', false);
    }

    public function test_home_gives_a_first_step_without_javascript_and_prefills_the_diagnosis(): void
    {
        $lesson = Lesson::query()->where('slug', 'core-message')->firstOrFail();
        $this->get('/?offer=عطور+نيش&problem=no_sales&budget=2000')
            ->assertOk()
            ->assertSee('أصلك الأول')
            ->assertSee('عطور نيش')
            ->assertSee($lesson->title)
            ->assertSee('أكمل التشخيص');

        $this->get('/assess?offer=عطور+نيش&problem=no_sales&budget=2000')->assertOk()->assertSee('value="عطور نيش"', false)->assertSee('value="2000"', false);
    }

    public function test_visitor_completes_the_three_stages_and_gets_a_priorities_map_with_a_real_lesson_link(): void
    {
        $this->assertSame(16, AssessmentQuestion::query()->count());
        $this->assertSame(30, AssessmentRule::query()->count());

        $this->get('/assess')->assertOk()->assertSee('عن مشروعك');
        $this->post('/assess/project', [
            'audience' => 'owner', 'offer' => 'منتجات عناية طبيعية للنساء في الرياض', 'sector' => 'beauty', 'city' => 'الرياض',
            'budget' => 3000, 'channel' => 'instagram', 'goal' => 'leads', 'goal_number' => 60,
        ])->assertRedirect('/assess/questions');

        $questions = $this->get('/assess/questions')->assertOk()->assertSee('ماذا تفعل الآن؟')->assertSee('3,000 ريال')->assertSee('إنستغرام');
        $ids = AssessmentQuestion::query()->where('audience', 'owner')->pluck('id');
        $this->assertCount(8, $ids);
        foreach ($ids as $id) {
            $questions->assertSee('name="q['.$id.']"', false);
        }

        $answers = [];
        foreach ($ids as $i => $id) {
            $answers['q'][$id] = $i < 2 ? 0 : 1; // weakest dimension: goal
        }
        $response = $this->post('/assess/submit', $answers)->assertRedirect();
        $result = AssessmentResult::query()->firstOrFail();
        $this->assertSame('goal', $result->weakest_dimension);
        $this->assertSame('beginner', $result->level);
        $this->assertCount(3, $result->priorities);
        $this->assertNotNull($result->start_lesson_id);
        $this->assertStringContainsString('t='.$result->session_token, $response->headers->get('Location'));

        $page = $this->get('/assess/result?t='.$result->session_token)->assertOk();
        $page->assertSee('خريطة الأولويات')->assertSee('الآن')->assertSee('هذا الشهر')->assertSee('بعدها')
            ->assertSee('/academy/marketing-foundations/what-marketing-really-is', false)
            ->assertSee('ابدأ بالأولوية 1')->assertSee('أبعادك مقابل مشاريع مشابهة')->assertSee('أثرك يُقاس هنا')
            ->assertSee('احفظ الخريطة بحساب')->assertSee('تنزيل Markdown')->assertSee('تنزيل PDF')
            ->assertSee('المقارنة المرجعية تظهر عند توفر 30 مشروعاً');
        $this->assertSame(1, AssessmentResult::query()->where('benchmark_group', 'owner:beauty:5000')->count());
    }

    public function test_registering_with_the_assessment_token_claims_the_map_and_builds_the_project_profile(): void
    {
        $result = AssessmentResult::query()->create([
            'audience' => 'owner', 'project_snapshot' => ['offer' => 'قهوة مختصة', 'budget' => 1500, 'channel' => 'whatsapp', 'goal' => 'leads', 'goal_number' => 30, 'sector' => 'food', 'city' => 'جدة'],
            'answers' => [], 'scores' => ['goal' => 20, 'audience' => 40, 'offer' => 50, 'channel' => 60, 'measure' => 30], 'overall' => 40, 'level' => 'beginner',
            'weakest_dimension' => 'goal', 'priorities' => [], 'benchmark_group' => 'owner:food:2000', 'session_token' => 'visitor-token-123',
        ]);

        $this->post('/register', [
            'name' => 'سارة', 'email' => 'sara@example.com', 'password' => 'StrongPass!2026', 'password_confirmation' => 'StrongPass!2026',
            'audience' => 'owner', 'assessment_token' => 'visitor-token-123',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'sara@example.com')->firstOrFail();
        $this->assertSame($user->id, $result->fresh()->user_id);
        $project = Project::query()->where('workspace_id', $user->workspaces()->first()->id)->firstOrFail();
        $this->assertSame('قهوة مختصة', $project->profile['offer']);
        $this->assertSame('whatsapp', $project->profile['channel']);
        $this->assertSame('owner', $user->fresh()->audience);
    }

    public function test_benchmark_appears_only_with_thirty_similar_projects(): void
    {
        $group = 'owner:beauty:5000';
        for ($i = 0; $i < 30; $i++) {
            AssessmentResult::query()->create([
                'audience' => 'owner', 'project_snapshot' => ['offer' => 'x', 'budget' => 3000, 'sector' => 'beauty'], 'answers' => [],
                'scores' => ['goal' => 50, 'audience' => 60, 'offer' => 40, 'channel' => 70, 'measure' => 30], 'overall' => 50, 'level' => 'intermediate',
                'weakest_dimension' => 'measure', 'priorities' => [['when' => 'الآن', 'title' => 't', 'reason' => 'r', 'link' => null, 'link_label' => null]],
                'benchmark_group' => $group, 'session_token' => 'tok-'.$i,
            ]);
        }
        $this->get('/assess/result?t=tok-3')->assertOk()->assertSee('متوسط 30 مشروعاً مشابهاً')->assertSee('class="mark"', false);
    }

    public function test_result_page_without_a_token_offers_a_new_diagnosis(): void
    {
        $this->get('/assess/result')->assertOk()->assertSee('لا توجد خريطة محفوظة');
        $this->assertSame(['beginner', 'intermediate', 'advanced'], array_keys(Catalog::LEVELS));
    }
}
