<?php

namespace App\Domain\Lab\Ai;

/**
 * OpenAI Responses API. Behaviour is kept byte-for-byte compatible with the single-provider gateway that preceded
 * the chain (POST {base}/responses, only a "completed" status is accepted, first output_text wins), so existing
 * runs, fakes and expectations keep passing. It stays in the chain but off by default on the free-tier stack.
 */
class OpenAiProvider extends HttpProvider
{
    public function configured(): bool
    {
        return $this->setting('key') !== '';
    }

    public function text(string $input, ?string $instructions, int $maxOutputTokens): array
    {
        $response = $this->request()
            ->withToken($this->setting('key'))
            ->post(rtrim($this->setting('base_url'), '/').'/responses', [
                'model' => $this->model(),
                'instructions' => $instructions ?? config('ai.default_instructions'),
                'input' => $input,
                'max_output_tokens' => $maxOutputTokens,
            ]);

        if (! $response->successful()) {
            $this->fail('HTTP '.$response->status(), $response->status());
        }
        if ($response->json('status') !== 'completed') {
            $this->fail('status='.(string) $response->json('status', 'unknown'));
        }

        $text = collect($response->json('output', []))
            ->flatMap(fn (array $item) => $item['content'] ?? [])
            ->firstWhere('type', 'output_text')['text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            $this->fail('completed without usable text');
        }

        return [
            'text' => trim($text),
            'input_tokens' => (int) $response->json('usage.input_tokens', 0),
            'output_tokens' => (int) $response->json('usage.output_tokens', 0),
            'model' => $this->model(),
        ];
    }
}
