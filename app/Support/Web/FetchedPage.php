<?php

namespace App\Support\Web;

use Illuminate\Support\Str;

/** نتيجة جلب واحدة: ما وصل، ومن أين، وهل كان من الكاش — يُمرَّر كقيمة لا كمصفوفة حتى لا يخمّن كل نداء مفاتيحها. */
class FetchedPage
{
    public function __construct(
        public readonly string $url,
        public readonly int $status,
        public readonly string $body,
        public readonly string $contentType = '',
        /** 'reader' when Jina returned it, 'direct' on the fallback, 'cache' when nothing left the server. */
        public readonly string $via = 'direct',
        public readonly bool $cached = false,
        public readonly ?string $title = null,
    ) {}

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300 && trim($this->body) !== '';
    }

    public function bytes(): int
    {
        return strlen($this->body);
    }

    /** The body as a model should read it: no markup, no runs of blank lines, capped. */
    public function text(?int $chars = null): string
    {
        $text = preg_replace('/\n{3,}/u', "\n\n", trim($this->body)) ?? trim($this->body);

        return Str::limit($text, $chars ?? (int) config('web.limits.max_text_chars', 40000), '…');
    }

    public function host(): string
    {
        return (string) (parse_url($this->url, PHP_URL_HOST) ?: '');
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url, 'status' => $this->status, 'via' => $this->via, 'cached' => $this->cached,
            'bytes' => $this->bytes(), 'title' => $this->title, 'content_type' => $this->contentType,
        ];
    }
}
