<?php

namespace App\Domain\Lab\Services;

use DomainException;
use Illuminate\Support\Facades\Http;

class AiGateway
{
    public function generate(string $input, ?string $instructions = null): array
    {
        $key = (string) config('services.openai.key');
        if ($key === '') {
            throw new DomainException('مزود الذكاء الاصطناعي غير مفعّل. لم يتم خصم أي رصيد.');
        }

        $response = Http::withToken($key)
            ->acceptJson()
            ->timeout(60)
            ->retry(2, 300, throw: false)
            ->post(rtrim((string) config('services.openai.base_url'), '/').'/responses', [
                'model' => config('services.openai.model'),
                'instructions' => $instructions ?? 'أنت مساعد تسويق عربي دقيق. افصل الحقائق عن الافتراضات، ولا تخترع أرقاماً أو مصادر.',
                'input' => $input,
            ]);

        if (! $response->successful() || $response->json('status') !== 'completed') {
            throw new DomainException('تعذر إكمال تشغيل الذكاء الاصطناعي. احتفظ النظام بالمدخلات ويمكن إعادة المحاولة.');
        }

        $text = collect($response->json('output', []))
            ->flatMap(fn (array $item) => $item['content'] ?? [])
            ->firstWhere('type', 'output_text')['text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            throw new DomainException('اكتمل الطلب دون مخرج نصي صالح.');
        }

        return [
            'text' => trim($text),
            'tokens' => (int) $response->json('usage.input_tokens', 0) + (int) $response->json('usage.output_tokens', 0),
            'model' => (string) config('services.openai.model'),
        ];
    }
}
