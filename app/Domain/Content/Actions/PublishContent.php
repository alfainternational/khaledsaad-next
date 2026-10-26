<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\ContentItem;
use DomainException;
use Illuminate\Support\Facades\DB;

class PublishContent
{
    public function handle(ContentItem $item): ContentItem
    {
        return DB::transaction(function () use ($item): ContentItem {
            $locked = ContentItem::query()->with('sources')->lockForUpdate()->findOrFail($item->id);

            if (in_array($locked->type, ['translated_analysis', 'news'], true)) {
                if (! $locked->source_mode || $locked->sources->isEmpty()) {
                    throw new DomainException('المادة العالمية تتطلب مصدراً وطريقة معالجة واضحة.');
                }
                if (! $locked->reviewer_name || ! $locked->reviewed_at) {
                    throw new DomainException('المراجعة البشرية مطلوبة قبل النشر.');
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
            $locked->update(['status' => 'published', 'published_at' => $locked->published_at ?? now()]);

            return $locked->refresh();
        });
    }
}
