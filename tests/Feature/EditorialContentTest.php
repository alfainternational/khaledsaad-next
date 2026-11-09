<?php

namespace Tests\Feature;

use App\Domain\Content\Models\ContentCategory;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\Tag;
use App\Support\FrontMatter;
use App\Support\Markdown;
use Database\Seeders\EditorialContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EditorialContentTest extends TestCase
{
    use RefreshDatabase;

    private function starter(): ContentItem
    {
        $original = (require database_path('content/editorial-baseline-state.php'))['customer-journey-map']['attrs'];
        $item = ContentItem::query()->create($original + [
            'author_name' => 'فريق أثر',
            'category_id' => ContentCategory::query()->firstOrCreate(['slug' => 'ads'], ['name' => 'Ads'])->id,
            'body' => "قسّم الرحلة إلى اكتشاف وتقييم وقرار واستخدام وعودة. في كل مرحلة سجّل سؤال العميل ودليله المطلوب والعائق الذي يمنعه من التقدم.\n\nاربط كل نقطة محتوى أو تواصل بفعل تالٍ واحد واضح.",
            'status' => 'draft', 'published_at' => '2026-01-01 10:00:00',
            'meta' => ['editor_note' => 'keep me'],
        ]);
        $item->sources()->create(['publisher' => 'منهجية أثر', 'title' => 'مرجع تحريري داخلي', 'url' => 'https://khaledsaad.net/methodology', 'published_on' => now()->subMonth(), 'language' => 'ar']);
        $item->update(['reviewed_at' => $item->created_at->copy()->subDays(3), 'last_reviewed_at' => $item->created_at->copy()->subDays(3)]);

        return $item;
    }

    public function test_release_preserves_identity_publication_and_recoverable_snapshot_and_is_idempotent(): void
    {
        $item = $this->starter();
        $original = $item->body;
        $source = $item->sources()->firstOrFail();
        $this->seed(EditorialContentSeeder::class);
        $item->refresh();
        $this->assertSame('draft', $item->status);
        $this->assertSame('2026-01-01 10:00:00', $item->published_at->format('Y-m-d H:i:s'));
        $this->assertNotSame($original, $item->body);
        $this->assertSame('reading-2026-10', $item->meta['editorial_revision']);
        $this->assertSame('keep me', $item->meta['editor_note']);
        $this->assertFalse($item->sources()->where('url', $source->url)->exists());
        $snapshot = json_decode(DB::table('content_editorial_revisions')->where('content_item_id', $item->id)->value('snapshot'), true);
        $this->assertSame($original, $snapshot['item']['body']);
        $this->assertSame($source->id, $snapshot['sources'][0]['id']);
        $this->assertSame('keep me', $snapshot['item']['meta']['editor_note']);
        $state = $item->getAttributes();
        $this->seed(EditorialContentSeeder::class);
        $this->assertSame($state, $item->fresh()->getAttributes());
        $this->assertSame(1, DB::table('content_editorial_revisions')->count());
    }

    public function test_editor_changes_are_reported_and_never_overwritten_even_after_release(): void
    {
        $item = $this->starter();
        $item->update(['body' => 'Editor owned text']);
        $this->artisan('db:seed', ['--class' => EditorialContentSeeder::class])
            ->expectsOutputToContain('CONFLICT customer-journey-map')->assertSuccessful();
        $this->assertSame('Editor owned text', $item->fresh()->body);
        $this->assertSame(0, DB::table('content_editorial_revisions')->count());

        $item->delete();
        $item = $this->starter();
        $this->seed(EditorialContentSeeder::class);
        $item->refresh()->update(['body' => 'Editor changes after release']);
        $this->artisan('db:seed', ['--class' => EditorialContentSeeder::class])
            ->expectsOutputToContain('CONFLICT customer-journey-map')->assertSuccessful();
        $this->assertSame('Editor changes after release', $item->fresh()->body);
        $this->assertSame(1, DB::table('content_editorial_revisions')->count());
    }

    public function test_unchanged_source_gets_actual_duration_without_inventing_a_review_date(): void
    {
        $item = $this->starter();
        $item->update(['last_reviewed_at' => '2026-01-02 00:00:00']);
        $body = $item->body;
        $seeder = new class($body) extends EditorialContentSeeder
        {
            public function __construct(private string $fixtureBody) {}

            protected function documents(): iterable
            {
                yield FrontMatter::parse("---\nslug: customer-journey-map\ntitle: Duration-only fixture\n---\n".$this->fixtureBody);
            }
        };
        $seeder->run();
        $this->assertSame(max(1, (int) ceil(Markdown::wordCount($body) / 180)), $item->fresh()->reading_minutes);
        $this->assertSame('2026-01-02', $item->fresh()->last_reviewed_at->format('Y-m-d'));
    }

    public function test_mutable_meta_hash_cannot_authorize_unknown_editor_body(): void
    {
        $item = $this->starter();
        $item->update(['body' => 'Unknown body', 'meta' => ['editorial_revision' => EditorialContentSeeder::REVISION, 'editorial_hash' => hash('sha256', 'Unknown body')]]);
        $this->seed(EditorialContentSeeder::class);
        $this->assertSame('Unknown body', $item->fresh()->body);
        $this->assertSame(0, DB::table('content_editorial_revisions')->count());
    }

    public function test_unchanged_body_does_not_authorize_replacing_editor_fields_sources_or_tags(): void
    {
        foreach (['title', 'excerpt', 'source', 'tag'] as $change) {
            $item = $this->starter();
            if ($change === 'source') {
                $item->sources()->firstOrFail()->update(['title' => 'Editor source']);
            } elseif ($change === 'tag') {
                $tag = Tag::query()->firstOrCreate(['slug' => 'editor-tag'], ['name' => 'Editor tag']);
                $item->tags()->attach($tag);
            } else {
                $item->update([$change => 'Editor '.$change]);
            }
            $before = $item->fresh()->attributesToArray();
            $sources = $item->sources()->get()->toArray();
            $tags = $item->tags()->pluck('tags.id')->all();
            $this->artisan('db:seed', ['--class' => EditorialContentSeeder::class])
                ->expectsOutputToContain('CONFLICT customer-journey-map')->assertSuccessful();
            $this->assertSame($before, $item->fresh()->attributesToArray(), $change);
            $this->assertSame($sources, $item->sources()->get()->toArray(), $change);
            $this->assertSame($tags, $item->tags()->pluck('tags.id')->all(), $change);
            $item->delete();
        }
        $this->assertSame(0, DB::table('content_editorial_revisions')->count());
    }

    public function test_after_release_metadata_edit_blocks_a_later_source_revision(): void
    {
        $item = $this->starter();
        $this->seed(EditorialContentSeeder::class);
        $item->refresh()->update(['excerpt' => 'Editor excerpt after release']);
        $body = $item->body;
        $seeder = new class($body) extends EditorialContentSeeder
        {
            public function __construct(private string $fixtureBody) {}

            protected function documents(): iterable
            {
                yield [['slug' => 'customer-journey-map', 'excerpt' => 'Next release excerpt', 'sources' => [['publisher' => 'NNGroup', 'title' => 'New reference', 'url' => 'https://www.nngroup.com/articles/']]], $this->fixtureBody];
            }
        };
        $seeder->run();
        $this->assertSame('Editor excerpt after release', $item->fresh()->excerpt);
        $this->assertSame($body, $item->fresh()->body);
        $this->assertSame(1, DB::table('content_editorial_revisions')->count());
    }

    public function test_approved_complete_state_allows_source_only_revision_and_then_is_a_no_op(): void
    {
        $item = $this->starter();
        $this->seed(EditorialContentSeeder::class);
        $body = $item->fresh()->body;
        $seeder = new class($body) extends EditorialContentSeeder
        {
            public function __construct(private string $fixtureBody) {}

            protected function documents(): iterable
            {
                yield [['slug' => 'customer-journey-map', 'sources' => [['publisher' => 'NNGroup', 'title' => 'New reference', 'url' => 'https://www.nngroup.com/articles/']]], $this->fixtureBody];
            }
        };
        $seeder->run();
        $this->assertSame('New reference', $item->sources()->firstOrFail()->title);
        $this->assertSame($body, $item->fresh()->body);
        $this->assertSame(2, DB::table('content_editorial_revisions')->count());
        $state = $item->fresh()->getAttributes();
        $seeder->run();
        $this->assertSame($state, $item->fresh()->getAttributes());
        $this->assertSame(2, DB::table('content_editorial_revisions')->count());
        $this->assertSame(64, strlen(DB::table('content_editorial_revisions')->latest('id')->value('after_state_hash')));
    }
}
