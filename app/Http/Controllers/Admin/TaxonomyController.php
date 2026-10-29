<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\Tag;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** التصنيفات والوسوم والمؤلفون — /admin/content/{categories|tags|authors} (blueprint §6.5): one standard table each, create/edit/delete with confirmation, audited. */
class TaxonomyController extends Controller
{
    public const KINDS = [
        'categories' => ['label' => 'التصنيفات', 'singular' => 'تصنيف', 'model' => ContentCategory::class, 'table' => 'content_categories'],
        'tags' => ['label' => 'الوسوم', 'singular' => 'وسم', 'model' => Tag::class, 'table' => 'tags'],
        'authors' => ['label' => 'المؤلفون', 'singular' => 'مؤلف', 'model' => Author::class, 'table' => 'authors'],
    ];

    public function index(Request $request, string $kind)
    {
        $this->authorizeAdmin($request);
        $meta = self::KINDS[$kind];
        $q = trim((string) $request->query('q', ''));
        $sort = in_array($request->query('sort'), ['name', 'items_count', 'updated_at'], true) ? $request->query('sort') : ($kind === 'categories' ? 'position' : 'name');
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $query = ($meta['model'])::query()->withCount('items');
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%"));
        }
        $rows = $query->orderBy($sort, $dir)->orderBy('id')->paginate(50)->withQueryString();
        $editing = $request->filled('edit') ? ($meta['model'])::query()->find((int) $request->query('edit')) : null;

        return view('admin.content.taxonomy', ['kind' => $kind, 'meta' => $meta, 'rows' => $rows, 'q' => $q, 'sort' => $sort, 'dir' => $dir, 'editing' => $editing, 'kinds' => self::KINDS]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $model = $this->save($request, $kind, new (self::KINDS[$kind]['model']));
        $this->audit($request, "content.{$kind}.created", $model);

        return redirect()->route('admin.content.taxonomy', $kind)->with('status', 'أُضيف '.self::KINDS[$kind]['singular'].' «'.$model->name.'».');
    }

    public function update(Request $request, string $kind, int $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $model = (self::KINDS[$kind]['model'])::query()->findOrFail($id);
        $before = $model->only(['name', 'slug']);
        $model = $this->save($request, $kind, $model);
        $this->audit($request, "content.{$kind}.updated", $model, ['before' => $before]);

        return redirect()->route('admin.content.taxonomy', $kind)->with('status', 'تم حفظ '.self::KINDS[$kind]['singular'].' «'.$model->name.'».');
    }

    public function destroy(Request $request, string $kind, int $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $request->validate(['confirm' => ['required', 'in:1']]);
        $model = (self::KINDS[$kind]['model'])::query()->withCount('items')->findOrFail($id);
        if ($kind === 'authors' && $model->items_count > 0) {
            return back()->withErrors(['delete' => 'لا يمكن حذف مؤلف له مواد؛ انقل مواده إلى مؤلف آخر أولاً.']);
        }
        $name = $model->name;
        $this->audit($request, "content.{$kind}.deleted", $model, ['items' => $model->items_count]);
        $model->delete();

        return redirect()->route('admin.content.taxonomy', $kind)->with('status', 'حُذف '.self::KINDS[$kind]['singular'].' «'.$name.'».');
    }

    private function save(Request $request, string $kind, Model $model): Model
    {
        $table = self::KINDS[$kind]['table'];
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', Rule::unique($table, 'slug')->ignore($model->getKey())],
        ];
        if ($kind === 'categories') {
            $rules += ['description' => ['nullable', 'string', 'max:600'], 'position' => ['nullable', 'integer', 'min:0', 'max:999']];
        }
        if ($kind === 'authors') {
            $rules += ['role' => ['nullable', 'string', 'max:120'], 'bio' => ['nullable', 'string', 'max:3000'], 'links' => ['nullable', 'string', 'max:2000']];
        }
        $data = $request->validate($rules);
        $data['slug'] = filled($data['slug'] ?? null) ? $data['slug'] : (Str::slug($data['name']) ?: Str::slug($data['name'], '-', 'ar') ?: 'item-'.Str::lower(Str::random(6)));
        if ($kind === 'authors') {
            // One link per line: "التسمية | https://…" or just the URL.
            $links = [];
            foreach (preg_split('/\R/u', (string) ($data['links'] ?? '')) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);
                if ($url === null) {
                    [$label, $url] = [parse_url($label, PHP_URL_HOST) ?: $label, $label];
                }
                if (preg_match('#^https?://#i', (string) $url)) {
                    $links[] = ['label' => $label, 'url' => $url];
                }
            }
            $data['links'] = $links;
        }
        $model->fill($data)->save();
        if ($kind === 'authors' && $model->wasChanged('name')) {
            ContentItem::query()->where('author_id', $model->id)->update(['author_name' => $model->name]);
        }

        return $model;
    }

    private function audit(Request $request, string $action, Model $model, array $context = []): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $model::class, 'subject_id' => (string) $model->getKey(),
            'context' => json_encode($context + ['name' => $model->name, 'slug' => $model->slug], JSON_UNESCAPED_UNICODE), 'request_id' => $request->attributes->get('request_id'), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()?->platform_role, ['editor', 'admin', 'super_admin'], true), 403);
    }
}
