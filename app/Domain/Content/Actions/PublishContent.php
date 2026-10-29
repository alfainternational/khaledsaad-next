<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Events\ContentPublished;
use App\Domain\Content\Models\ContentItem;
use DomainException;
use Illuminate\Support\Facades\DB;

class PublishContent
{
    public function handle(ContentItem $item, ?int $actorId = null): ContentItem
    {
        return DB::transaction(function () use ($item, $actorId): ContentItem {
            $locked = ContentItem::query()->with('sources')->lockForUpdate()->findOrFail($item->id);

            if (! in_array($locked->status, ['review', 'seo_review'], true)) {
                throw new DomainException('يجب أن تمر المادة بالمراجعة التحريرية قبل النشر.');
            }

            if ($locked->requiresSources()) {
                if (! $locked->source_mode || $locked->sources->isEmpty()) {
                    throw new DomainException('المادة العالمية تتطلب مصدراً وطريقة معالجة واضحة.');
                }
                if (! $locked->reviewer_name || ! $locked->reviewed_at) {
                    throw new DomainException('المراجعة البشرية مطلوبة قبل النشر.');
                }
                if (blank(data_get($locked->meta, 'arabic_context'))) {
                    throw new DomainException('ملخص السياق العربي مطلوب قبل نشر المادة العالمية.');
                }
            }

            if (blank($locked->title) || blank($locked->excerpt) || blank($locked->body)) {
                throw new DomainException('العنوان والملخص والمحتوى مطلوبة.');
            }

            $version = DB::table('content_versions')->where('content_item_id', $locked->id)->max('version') + 1;
            DB::table('content_versions')->insert([
                'content_item_id' => $locked->id,
                'version' => $version,
                'snapshot' => json_encode($locked->attributesToArray(), JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $locked->update([
                'status' => 'published',
                'published_at' => $locked->published_at ?? now(),
                'translation_stage' => 'published',
                'last_reviewed_at' => $locked->last_reviewed_at ?? $locked->reviewed_at ?? now(),
            ]);
            DB::table('audit_logs')->insert([
                'user_id' => $actorId,
                'action' => 'content.published',
                'subject_type' => ContentItem::class,
                'subject_id' => (string) $locked->id,
                'context' => json_encode(['version' => $version], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::afterCommit(fn () => ContentPublished::dispatch($locked->id, $version));

            return $locked->refresh();
        });
    }
}
