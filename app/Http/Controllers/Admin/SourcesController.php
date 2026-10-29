<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Content\Actions\PublishContent;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\SourceFeed;
use App\Domain\Content\Models\SourceItem;
use App\Domain\Content\Services\SourceFetcher;
use App\Domain\Content\Services\SourceSummarizer;
use App\Domain\Lab\Models\Tool;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * من المصادر إلى الرؤى — /admin/sources (blueprint §6.3): the five-gate board
 * التقاط → تحقق → ملخص → سياق عربي → مراجعة ونشر, with refresh reminders for items whose numbers are older than 12 months.
 */
class SourcesController extends Controller
{
    public const CHECKLIST = [
        'primary' => 'كل رقم في المادة يعود إلى مصدر أولي مذكور في قائمة المصادر.',
        'context' => 'السياق العربي 120 كلمة فأكثر ويذكر مثالاً محلياً واحداً على الأقل.',
        'links' => 'الدرس والأداة المرتبطان مناسبان لقرار القارئ لا للموضوع فقط.',
        'voice' => 'المادة تحليل عربي لا ترجمة؛ العنوان سؤال حقيقي و«في دقيقة» تجيب عنه.',
        'freshness' => 'تاريخ المصدر مذكور، وأي رقم أقدم من 12 شهراً مُعلَّم بتاريخه.',
    ];

    public function index(Request $request, SourceFetcher $fetcher)
    {
        $this->authorizeEditor($request);
        $gates = [];
        foreach (SourceItem::GATES as $gate) {
            $gates[$gate] = SourceItem::query()->with('feed')->where('stage', $gate)->orderByDesc('relevance_score')->orderByDesc('id')->limit(12)->get();
        }
        $counts = SourceItem::query()->selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage');

        return view('admin.sources.index', [
            'gates' => $gates, 'counts' => $counts, 'labels' => SourceItem::STAGES,
            'feeds' => SourceFeed::query()->withCount('items')->orderBy('name')->get(),
            'published' => SourceItem::query()->with('contentItem')->where('stage', 'published')->latest('updated_at')->limit(8)->get(),
            'ignored' => SourceItem::query()->where('stage', 'ignored')->count(),
            'stale' => $this->staleItems(),
            'fetchedAt' => cache()->get('platform:sources_fetched_at'),
            'aiEnabled' => SourceSummarizer::aiEnabled(),
            'rules' => $this->gateRules(),
        ]);
    }

    public function show(Request $request, SourceItem $item)
    {
        $this->authorizeEditor($request);
        $item->load(['feed', 'contentItem', 'relatedLesson.course', 'relatedTool']);

        return view('admin.sources.show', [
            'item' => $item, 'labels' => SourceItem::STAGES, 'gates' => SourceItem::GATES, 'checklist' => self::CHECKLIST, 'rules' => $this->gateRules(),
            'lessons' => Lesson::query()->with('course')->orderBy('course_id')->orderBy('position')->get()->map(fn (Lesson $l) => ['id' => $l->id, 'label' => ($l->course?->title ?? '').' › '.$l->title]),
            'tools' => Tool::query()->where('slug', '!=', 'lesson-lab')->where('is_published', true)->orderBy('kind')->orderBy('position')->get(['id', 'name', 'kind']),
            'categories' => ContentCategory::query()->orderBy('position')->get(), 'authors' => Author::query()->orderBy('id')->get(),
            'aiEnabled' => SourceSummarizer::aiEnabled(), 'minWords' => SourceItem::MIN_CONTEXT_WORDS,
            'audit' => DB::table('audit_logs')->where('subject_type', SourceItem::class)->where('subject_id', (string) $item->id)->orderByDesc('id')->limit(12)->get(),
        ]);
    }

    /** Pull the watched feeds now (same code path as the daily command). */
    public function fetch(Request $request): RedirectResponse
    {
        $this->authorizeEditor($request);
        Artisan::call('platform:fetch-sources');
        $this->audit($request, 'sources.fetched', null, ['output' => Str::limit(trim(Artisan::output()), 600)]);

        return redirect()->route('admin.sources.index')->with('status', trim(collect(explode("\n", Artisan::output()))->last() ?: 'تم الجلب.'));
    }

    /** Manual link (§6.3 «روابط يدوية»). */
    public function store(Request $request, SourceFetcher $fetcher): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:1000'], 'title' => ['nullable', 'string', 'max:290'], 'publisher' => ['nullable', 'string', 'max:160'],
            'excerpt' => ['nullable', 'string', 'max:2000'], 'language' => ['nullable', Rule::in(['en', 'ar', 'fr', 'de', 'es'])], 'published_on' => ['nullable', 'date'],
        ]);
        $item = $fetcher->capture([
            'url' => $data['url'], 'title' => ($data['title'] ?? null) ?: $data['url'], 'publisher' => ($data['publisher'] ?? null) ?: (parse_url($data['url'], PHP_URL_HOST) ?: 'مصدر'),
            'excerpt' => $data['excerpt'] ?? '', 'language' => $data['language'] ?? 'en', 'published_on' => $data['published_on'] ?? null,
        ], null, $request->user()->id);
        if (! $item) {
            return back()->withErrors(['url' => 'هذا الرابط ملتقط من قبل (يُتجاهل المكرر تلقائياً).'])->withInput();
        }
        $this->audit($request, 'sources.item.added', $item, ['url' => $item->url, 'score' => $item->relevance_score]);

        return redirect()->route('admin.sources.show', $item)->with('status', "التُقط الرابط بدرجة صلة {$item->relevance_score}. البوابة التالية: التحقق.");
    }

    /** Gate 2 — تحقق: primary source, date, market fit. A number without a primary source does not pass. */
    public function verify(Request $request, SourceItem $item): RedirectResponse
    {
        $this->authorizeEditor($request);
        $this->requireStage($item, ['captured', 'verified']);
        $data = $request->validate([
            'has_numbers' => ['nullable', 'boolean'], 'primary_source' => ['nullable', 'string', 'max:600'],
            'published_on' => ['required', 'date', 'before_or_equal:today'], 'market_fit' => ['required', 'string', 'min:10', 'max:600'],
            'publisher' => ['nullable', 'string', 'max:160'], 'title' => ['nullable', 'string', 'max:290'],
        ], ['market_fit.required' => 'اكتب كيف ينطبق المصدر على السوق العربي (أو لماذا لا ينطبق).', 'published_on.required' => 'تاريخ المصدر مطلوب للتحقق.']);
        if (! empty($data['has_numbers']) && blank($data['primary_source'])) {
            return back()->withErrors(['primary_source' => 'المصدر يحوي أرقاماً: اذكر المصدر الأولي للرقم وإلا لا يمر من بوابة التحقق.'])->withInput();
        }
        $item->forceFill([
            'stage' => 'verified', 'published_on' => $data['published_on'],
            'publisher' => ($data['publisher'] ?? null) ?: $item->publisher, 'title' => ($data['title'] ?? null) ?: $item->title,
            'verification' => ['has_numbers' => (bool) ($data['has_numbers'] ?? false), 'primary_source' => $data['primary_source'] ?? null, 'market_fit' => $data['market_fit'], 'verified_by' => $request->user()->name, 'verified_at' => now()->toDateTimeString()],
        ])->save();
        $this->audit($request, 'sources.item.verified', $item, ['has_numbers' => (bool) ($data['has_numbers'] ?? false)]);

        return redirect()->route('admin.sources.show', $item)->with('status', 'مرّ المصدر من بوابة التحقق. التالي: الملخص.');
    }

    /** Gate 3 — ملخص: rules now, AI when a key exists. Never published as is. */
    public function summarize(Request $request, SourceItem $item, SourceSummarizer $summarizer): RedirectResponse
    {
        $this->authorizeEditor($request);
        $this->requireStage($item, ['verified', 'summarized']);
        $useAi = (bool) $request->boolean('ai');
        if ($request->filled('summary')) {
            $request->validate(['summary' => ['string', 'max:8000']]);
            $item->forceFill(['summary' => $request->string('summary')->toString(), 'summary_mode' => 'editor', 'stage' => 'summarized'])->save();
            $this->audit($request, 'sources.item.summarized', $item, ['mode' => 'editor']);

            return redirect()->route('admin.sources.show', $item)->with('status', 'حُفظ الملخص. التالي: السياق العربي.');
        }
        $result = $summarizer->summarize($item, $useAi);
        $item->forceFill(['summary' => $result['text'], 'summary_mode' => $result['mode'], 'stage' => 'summarized'])->save();
        $this->audit($request, 'sources.item.summarized', $item, ['mode' => $result['mode']]);
        $note = $result['mode'] === 'ai' ? 'وُلّد الملخص بالذكاء الاصطناعي.' : ($useAi ? 'لا مفتاح ذكاء اصطناعي؛ وُلّد الملخص بالقواعد.' : 'وُلّد الملخص بالقواعد.');

        return redirect()->route('admin.sources.show', $item)->with('status', $note.' مادة خام للكاتب: لا تُنشر كما هي. التالي: السياق العربي.');
    }

    /** Gate 4 — سياق عربي: 120+ words, a local example, a lesson and a tool → becomes a translated_analysis draft at stage contextualized. */
    public function contextualize(Request $request, SourceItem $item): RedirectResponse
    {
        $this->authorizeEditor($request);
        $this->requireStage($item, ['summarized', 'contextualized']);
        $data = $request->validate([
            'arabic_context' => ['required', 'string', 'max:8000'], 'local_example' => ['required', 'string', 'min:10', 'max:600'],
            'related_lesson_id' => ['required', 'integer', Rule::exists('lessons', 'id')], 'related_tool_id' => ['required', 'integer', Rule::exists('tools', 'id')],
            'title' => ['required', 'string', 'max:200'], 'tldr' => ['required', 'string', 'max:600'], 'category_id' => ['nullable', 'integer', Rule::exists('content_categories', 'id')],
            'author_id' => ['nullable', 'integer', Rule::exists('authors', 'id')],
        ], ['local_example.required' => 'المثال المحلي إلزامي.', 'related_lesson_id.required' => 'اختر الدرس المرتبط.', 'related_tool_id.required' => 'اختر الأداة المرتبطة.']);
        $words = str_word_count_utf8($data['arabic_context']);
        if ($words < SourceItem::MIN_CONTEXT_WORDS) {
            return back()->withErrors(['arabic_context' => 'السياق العربي '.$words.' كلمة؛ الحد الأدنى '.SourceItem::MIN_CONTEXT_WORDS.' كلمة.'])->withInput();
        }

        $content = DB::transaction(function () use ($item, $data, $request) {
            $author = ! empty($data['author_id']) ? Author::query()->find($data['author_id']) : null;
            $body = $this->composeBody($item, $data);
            $slug = $this->uniqueSlug($data['title'], $item->content_item_id);
            $attributes = [
                'type' => 'translated_analysis', 'section' => 'insights', 'title' => $data['title'], 'slug' => $slug, 'eyebrow' => 'رؤية عالمية',
                'excerpt' => Str::limit($data['tldr'], 220, '…'), 'tldr' => $data['tldr'], 'body' => $body,
                'author_name' => $author?->name ?? $request->user()->name, 'author_id' => $author?->id, 'category_id' => $data['category_id'] ?? null,
                'topic' => $item->feed?->topic, 'source_mode' => 'analysis', 'translation_stage' => 'contextualized',
                'related_lesson_id' => $data['related_lesson_id'], 'related_tool_id' => $data['related_tool_id'],
                'reading_minutes' => max(3, (int) ceil(str_word_count_utf8($body) / 180)),
                'meta' => ['arabic_context' => Str::limit($data['arabic_context'], 1500, ''), 'local_example' => $data['local_example'], 'source_item_id' => $item->id, 'summary' => $item->summary],
            ];
            if ($item->content_item_id && ($existing = ContentItem::query()->find($item->content_item_id)) && $existing->status !== 'published') {
                $existing->fill(array_diff_key($attributes, ['slug' => 1]))->save();
                $content = $existing;
            } else {
                $content = ContentItem::query()->create($attributes + ['status' => 'draft']);
            }
            $content->sources()->delete();
            $content->sources()->create(['publisher' => $item->publisherName(), 'title' => $item->title, 'url' => $item->url, 'published_on' => $item->published_on, 'language' => $item->language]);
            if ($primary = data_get($item->verification, 'primary_source')) {
                if (preg_match('#https?://\S+#', $primary, $m)) {
                    $content->sources()->create(['publisher' => 'المصدر الأولي', 'title' => Str::limit(trim(str_replace($m[0], '', $primary)) ?: 'المصدر الأولي للأرقام', 200), 'url' => rtrim($m[0], '.,)'), 'published_on' => $item->published_on, 'language' => $item->language]);
                }
            }
            $item->forceFill([
                'stage' => 'contextualized', 'arabic_context' => $data['arabic_context'], 'local_example' => $data['local_example'],
                'related_lesson_id' => $data['related_lesson_id'], 'related_tool_id' => $data['related_tool_id'], 'content_item_id' => $content->id,
            ])->save();

            return $content;
        });
        $this->audit($request, 'sources.item.contextualized', $item, ['content_item_id' => $content->id, 'words' => $words]);

        return redirect()->route('admin.sources.show', $item)->with('status', "أُنشئت مادة من نوع «رؤية عالمية» (مسودة #{$content->id}) بمرحلة «سياق عربي». التالي: المراجعة والنشر.");
    }

    /** Gate 5 — مراجعة ونشر: five-point checklist + reviewer name, then PublishContent. */
    public function review(Request $request, SourceItem $item, PublishContent $publisher): RedirectResponse
    {
        $this->authorizeEditor($request);
        $this->requireStage($item, ['contextualized', 'reviewed']);
        $data = $request->validate([
            'checks' => ['required', 'array'], 'checks.*' => ['in:1'], 'reviewer_name' => ['required', 'string', 'max:120'], 'action' => ['required', Rule::in(['approve', 'publish'])],
        ], ['checks.required' => 'قائمة الفحص الخمسية مطلوبة كاملة.']);
        $missing = array_diff(array_keys(self::CHECKLIST), array_keys($data['checks']));
        if ($missing !== []) {
            return back()->withErrors(['checks' => 'لم تُستوفَ كل بنود الفحص: '.implode('، ', array_map(fn ($k) => self::CHECKLIST[$k], $missing))])->withInput();
        }
        $content = $item->content_item_id ? ContentItem::query()->find($item->content_item_id) : null;
        if (! $content) {
            return back()->withErrors(['review' => 'لا مادة مرتبطة بهذا المصدر؛ أعد بوابة السياق العربي.']);
        }
        $content->forceFill(['reviewer_name' => $data['reviewer_name'], 'reviewed_at' => now(), 'last_reviewed_at' => now(), 'translation_stage' => 'reviewed', 'status' => $content->status === 'published' ? 'published' : 'review'])->save();
        $item->forceFill(['stage' => 'reviewed'])->save();
        $this->audit($request, 'sources.item.reviewed', $item, ['reviewer' => $data['reviewer_name'], 'checks' => array_keys($data['checks'])]);

        if ($data['action'] === 'publish') {
            try {
                $publisher->handle($content, $request->user()->id);
            } catch (DomainException $e) {
                return back()->withErrors(['review' => $e->getMessage()]);
            }
            $item->forceFill(['stage' => 'published'])->save();
            $this->audit($request, 'sources.item.published', $item, ['content_item_id' => $content->id]);

            return redirect()->route('admin.sources.index')->with('status', 'نُشرت المادة «'.$content->title.'» وحُفظت نسخة منها.');
        }

        return redirect()->route('admin.sources.show', $item)->with('status', 'اعتُمدت المراجعة باسم '.$data['reviewer_name'].'. المادة جاهزة للنشر.');
    }

    public function ignore(Request $request, SourceItem $item): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate(['reason' => ['required', 'string', 'max:160']]);
        $item->forceFill(['stage' => 'ignored', 'ignored_reason' => $data['reason']])->save();
        $this->audit($request, 'sources.item.ignored', $item, ['reason' => $data['reason']]);

        return redirect()->route('admin.sources.index')->with('status', 'تم تجاهل المصدر: '.$data['reason']);
    }

    public function toggleFeed(Request $request, SourceFeed $feed): RedirectResponse
    {
        $this->authorizeEditor($request);
        $feed->forceFill(['is_active' => ! $feed->is_active])->save();
        $this->audit($request, 'sources.feed.toggled', null, ['feed' => $feed->name, 'active' => $feed->is_active]);

        return back()->with('status', ($feed->is_active ? 'فُعّل' : 'أُوقف').' مصدر «'.$feed->name.'».');
    }

    private function composeBody(SourceItem $item, array $data): string
    {
        $date = $item->published_on?->format('Y/m/d') ?? 'بلا تاريخ';
        $summary = trim(preg_replace('/^— ملخص آلي.*$/mu', '', (string) $item->summary) ?? '');

        return "## ماذا يقول المصدر؟\n\nبحسب {$item->publisherName()} ({$date})، «{$item->title}». ".($summary !== '' ? "\n\n".$summary : '')."\n\n".
            "## ماذا يعني هذا في السوق العربي؟\n\n{$data['arabic_context']}\n\n### مثال محلي\n\n{$data['local_example']}\n\n".
            "## ما الذي لا يقوله المصدر؟\n\n".(data_get($item->verification, 'market_fit') ?: 'انطباق النتائج محلياً يحتاج اختباراً على مشروعك.')."\n";
    }

    private function uniqueSlug(string $title, ?int $existingId): string
    {
        $base = Str::slug($title) ?: Str::slug($title, '-', 'ar') ?: 'insight';
        $base = Str::limit($base, 120, '');
        $slug = $base;
        $i = 2;
        while (ContentItem::query()->where('slug', $slug)->when($existingId, fn ($q) => $q->where('id', '!=', $existingId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function requireStage(SourceItem $item, array $allowed): void
    {
        abort_unless(in_array($item->stage, $allowed, true), 422, 'هذه البوابة لا تقبل العنصر في مرحلته الحالية ('.$item->stageLabel().').');
    }

    private function staleItems()
    {
        return ContentItem::published()->whereIn('type', ContentItem::SOURCED_TYPES)->with('sources')->get()->filter->hasStaleSources()->sortBy(fn ($i) => $i->oldestSourceDate())->values();
    }

    private function gateRules(): array
    {
        return [
            'captured' => '14 مصدراً مراقباً + روابط يدوية. درجة صلة آلية، والمكرر يُتجاهل تلقائياً.',
            'verified' => 'المصدر الأولي، التاريخ، انطباق على السوق. رقم بلا مصدر أولي لا يمر.',
            'summarized' => 'ملخص آلي بالقواعد (وبالذكاء الاصطناعي عند وجود مفتاح). مادة خام للكاتب لا تُنشر أبداً.',
            'contextualized' => 'سياق عربي إلزامي 120 كلمة+، مثال محلي، درس وأداة مرتبطان → مسودة «رؤية عالمية».',
            'reviewed' => 'قائمة فحص خمسية واسم المراجع، ثم النشر بنسخة محفوظة.',
        ];
    }

    private function audit(Request $request, string $action, ?SourceItem $item, array $context): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => $action, 'subject_type' => SourceItem::class, 'subject_id' => $item ? (string) $item->id : null,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE), 'request_id' => $request->attributes->get('request_id'), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function authorizeEditor(Request $request): void
    {
        abort_unless(in_array($request->user()?->platform_role, ['editor', 'admin', 'super_admin'], true), 403);
    }
}
