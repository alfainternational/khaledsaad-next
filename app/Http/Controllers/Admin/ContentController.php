<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Content\Actions\PublishContent;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\Tag;
use App\Domain\Lab\Models\Tool;
use App\Http\Controllers\Controller;
use App\Support\Markdown;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * إدارة المحتوى الموسعة — /admin/content (blueprint §6.5): a searchable, sortable table of items filtered by section,
 * status and translation stage; an editor that sets section, category, tags, author, reviewer, tldr, related lesson/tool,
 * FAQ and sources; «نظّف الفقرات» for imported items. Every change lands in audit_logs.
 */
class ContentController extends Controller
{
    public const TYPES = ContentItem::TYPES;

    /** Editorial states an editor may set by hand; "published" is only reachable through PublishContent. */
    public const EDITABLE_STATUSES = ['draft', 'review', 'seo_review'];

    public const STATUS_LABELS = ['draft' => 'مسودة', 'review' => 'جاهزة للمراجعة', 'seo_review' => 'مراجعة SEO', 'published' => 'منشور'];

    private const SORTS = ['updated_at', 'published_at', 'title', 'reading_minutes', 'status'];

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'section' => (string) $request->query('section', ''),
            'status' => (string) $request->query('status', ''),
            'stage' => (string) $request->query('stage', ''),
            'sort' => in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'updated_at',
            'dir' => $request->query('dir') === 'asc' ? 'asc' : 'desc',
        ];
        $query = ContentItem::query()->with(['author', 'category'])->withCount('sources');
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%")->orWhere('excerpt', 'like', "%{$q}%"));
        }
        if (array_key_exists($filters['section'], ContentItem::SECTIONS)) {
            $query->where('section', $filters['section']);
        } else {
            $filters['section'] = '';
        }
        if (array_key_exists($filters['status'], self::STATUS_LABELS)) {
            $query->where('status', $filters['status']);
        } else {
            $filters['status'] = '';
        }
        if (array_key_exists($filters['stage'], ContentItem::STAGES)) {
            $query->where('translation_stage', $filters['stage']);
        } else {
            $filters['stage'] = '';
        }
        $query->orderBy($filters['sort'], $filters['dir'])->orderByDesc('id');

        return view('admin.content.index', [
            'items' => $query->paginate(30)->withQueryString(),
            'filters' => $filters,
            'counts' => ContentItem::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'sectionCounts' => ContentItem::query()->selectRaw('section, count(*) as n')->groupBy('section')->pluck('n', 'section'),
            'statuses' => self::STATUS_LABELS,
            'stale' => $this->staleItems(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);
        $item = new ContentItem(['type' => 'article', 'section' => 'articles', 'status' => 'draft', 'reading_minutes' => 6, 'author_name' => $request->user()->name]);

        return view('admin.content.edit', $this->editorData($item) + ['creating' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $item = new ContentItem(['status' => 'draft']);
        $data = $this->validated($request, $item);
        $this->apply($item, $data, $request);
        $this->audit($request, 'content.created', $item, ['title' => $item->title]);

        return redirect()->route('admin.content.edit', $item)->with('status', 'أُنشئت المادة كمسودة.');
    }

    public function edit(Request $request, ContentItem $contentItem)
    {
        $this->authorizeAdmin($request);
        $contentItem->load(['sources', 'tags']);

        return view('admin.content.edit', $this->editorData($contentItem) + ['creating' => false]);
    }

    public function update(Request $request, ContentItem $contentItem): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->validated($request, $contentItem);
        $before = $contentItem->only(array_keys(array_intersect_key($data, $contentItem->getAttributes())));
        $this->apply($contentItem, $data, $request);
        $changed = array_keys(array_filter($data, fn ($v, $k) => array_key_exists($k, $before) && $before[$k] != $v, ARRAY_FILTER_USE_BOTH));
        $this->audit($request, 'content.updated', $contentItem, ['changed' => $changed]);

        return redirect()->route('admin.content.edit', $contentItem)->with('status', 'تم حفظ التعديلات.');
    }

    public function destroy(Request $request, ContentItem $contentItem): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $request->validate(['confirm' => ['required', 'in:1']]);
        $title = $contentItem->title;
        $id = $contentItem->id;
        $contentItem->delete();
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => 'content.deleted', 'subject_type' => ContentItem::class, 'subject_id' => (string) $id,
            'context' => json_encode(['title' => $title], JSON_UNESCAPED_UNICODE), 'request_id' => $request->attributes->get('request_id'), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('admin.content.index')->with('status', "حُذفت المادة «{$title}».");
    }

    public function publish(Request $request, ContentItem $contentItem, PublishContent $publisher): RedirectResponse
    {
        $this->authorizeAdmin($request);
        try {
            $publisher->handle($contentItem, $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['publish' => $e->getMessage()]);
        }

        return back()->with('status', 'تم نشر المادة وحفظ نسخة منها.');
    }

    /** «نظّف الفقرات» (§4.11 acceptance): merge broken lines of imported bodies into clean paragraphs; the previous body is kept in meta. */
    public function clean(Request $request, ContentItem $contentItem): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $cleaned = Markdown::cleanParagraphs((string) $contentItem->body);
        if ($cleaned === $contentItem->body) {
            return back()->with('info', 'الفقرات نظيفة بالفعل؛ لا تغيير.');
        }
        $meta = $contentItem->meta ?? [];
        $meta['body_before_clean'] = $contentItem->body;
        $contentItem->forceFill(['body' => $cleaned, 'meta' => $meta])->save();
        $this->audit($request, 'content.cleaned', $contentItem, ['paragraphs' => substr_count($cleaned, "\n\n") + 1]);

        return back()->with('status', 'نُظِّفت الفقرات. النسخة السابقة محفوظة في بيانات المادة.');
    }

    private function validated(Request $request, ContentItem $item): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', Rule::unique('content_items', 'slug')->ignore($item->id)],
            'type' => ['required', Rule::in(self::TYPES)],
            'section' => ['nullable', Rule::in(array_keys(ContentItem::SECTIONS))], // blank = derived from type (model hook)
            'status' => ['required', Rule::in($item->status === 'published' ? ['published'] : self::EDITABLE_STATUSES)],
            'translation_stage' => ['nullable', Rule::in(array_keys(ContentItem::STAGES))],
            'category_id' => ['nullable', 'integer', Rule::exists('content_categories', 'id')],
            'author_id' => ['nullable', 'integer', Rule::exists('authors', 'id')],
            'tags' => ['nullable', 'array', 'max:8'], 'tags.*' => ['integer', Rule::exists('tags', 'id')],
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'topic' => ['nullable', 'string', 'max:80'],
            'excerpt' => ['required', 'string', 'max:600'],
            'tldr' => ['nullable', 'string', 'max:600'],
            'body' => ['required', 'string', 'max:200000'],
            'author_name' => ['required', 'string', 'max:120'],
            'reviewer_name' => ['nullable', 'string', 'max:120'],
            'reading_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'related_lesson_id' => ['nullable', 'integer', Rule::exists('lessons', 'id')],
            'related_tool_id' => ['nullable', 'integer', Rule::exists('tools', 'id')],
            'source_mode' => ['nullable', 'string', 'max:40'],
            'arabic_context' => ['nullable', 'string', 'max:2000'],
            'faq' => ['nullable', 'array', 'max:10'], 'faq.*.q' => ['nullable', 'string', 'max:300'], 'faq.*.a' => ['nullable', 'string', 'max:1000'],
            'sources' => ['nullable', 'array', 'max:12'],
            'sources.*.publisher' => ['nullable', 'string', 'max:160'], 'sources.*.title' => ['nullable', 'string', 'max:300'],
            'sources.*.url' => ['nullable', 'url:http,https', 'max:1000'], 'sources.*.date' => ['nullable', 'date'], 'sources.*.language' => ['nullable', 'string', 'max:8'],
            'mark_reviewed' => ['nullable', 'boolean'],
        ];

        return $request->validate($rules);
    }

    private function apply(ContentItem $item, array $data, Request $request): void
    {
        DB::transaction(function () use ($item, $data, $request): void {
            $faq = [];
            foreach ($data['faq'] ?? [] as $f) {
                if (filled($f['q'] ?? null) && filled($f['a'] ?? null)) {
                    $faq[] = ['q' => trim($f['q']), 'a' => trim($f['a'])];
                }
            }
            $meta = $item->meta ?? [];
            if (array_key_exists('arabic_context', $data)) {
                $meta['arabic_context'] = filled($data['arabic_context']) ? trim($data['arabic_context']) : null;
                if ($meta['arabic_context'] === null) {
                    unset($meta['arabic_context']);
                }
            }
            $author = ! empty($data['author_id']) ? Author::query()->find($data['author_id']) : null;
            $item->fill(array_diff_key($data, array_flip(['tags', 'faq', 'sources', 'arabic_context', 'mark_reviewed'])));
            $item->faq = $faq === [] ? null : $faq;
            $item->meta = $meta;
            if ($author) {
                $item->author_name = $author->name;
            }
            $item->source_mode = $item->source_mode ?: ($item->requiresSources() ? 'analysis' : 'original');
            if (! empty($data['reviewer_name']) && ! $item->reviewed_at) {
                $item->reviewed_at = now();
            }
            if (! empty($data['mark_reviewed'])) {
                $item->last_reviewed_at = now();
                $item->reviewed_at = $item->reviewed_at ?? now();
            }
            if (! $item->exists) {
                $item->slug = $item->slug ?: Str::slug($item->title, '-', 'ar');
            }
            $item->save();
            $item->tags()->sync(array_map('intval', $data['tags'] ?? []));

            if (array_key_exists('sources', $data)) {
                $item->sources()->delete();
                foreach ($data['sources'] ?? [] as $s) {
                    if (blank($s['url'] ?? null)) {
                        continue;
                    }
                    $item->sources()->create([
                        'publisher' => $s['publisher'] ?: (parse_url($s['url'], PHP_URL_HOST) ?: 'مصدر'), 'title' => $s['title'] ?: ($s['publisher'] ?: 'المصدر'),
                        'url' => $s['url'], 'published_on' => $s['date'] ?: null, 'language' => $s['language'] ?: 'en',
                    ]);
                }
            }
        });
    }

    private function editorData(ContentItem $item): array
    {
        return [
            'item' => $item, 'types' => ContentItem::TYPE_LABELS, 'statuses' => self::EDITABLE_STATUSES, 'statusLabels' => self::STATUS_LABELS,
            'sections' => ContentItem::SECTIONS, 'stages' => ContentItem::STAGES,
            'categories' => ContentCategory::query()->orderBy('position')->get(), 'authors' => Author::query()->orderBy('id')->get(), 'tags' => Tag::query()->orderBy('name')->get(),
            'lessons' => Lesson::query()->with('course')->orderBy('course_id')->orderBy('position')->get()->map(fn (Lesson $l) => ['id' => $l->id, 'label' => ($l->course?->title ?? '').' › '.$l->title]),
            'tools' => Tool::query()->where('slug', '!=', 'lesson-lab')->orderBy('kind')->orderBy('position')->get(['id', 'name', 'kind']),
            'audit' => $item->exists ? DB::table('audit_logs')->where('subject_type', ContentItem::class)->where('subject_id', (string) $item->id)->orderByDesc('id')->limit(10)->get() : collect(),
            'stale' => $item->exists && $item->hasStaleSources(),
        ];
    }

    /** Published items whose numbers rest on sources older than twelve months (§6.3 refresh reminders). */
    private function staleItems()
    {
        return ContentItem::published()->whereIn('type', ContentItem::SOURCED_TYPES)->with('sources')->get()->filter->hasStaleSources()->values();
    }

    private function audit(Request $request, string $action, ContentItem $item, array $context): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => $action, 'subject_type' => ContentItem::class, 'subject_id' => (string) $item->id,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE), 'request_id' => $request->attributes->get('request_id'), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()?->platform_role, ['editor', 'admin', 'super_admin'], true), 403);
    }
}
