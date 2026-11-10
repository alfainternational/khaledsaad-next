<?php

namespace App\Domain\Lab\Ai;

/**
 * Cloudflare Workers AI. Second in the chain, and the designated destination for sensitive runs: the vendor does
 * not train on submitted input, so a client's own numbers and contracts can be processed without the free-tier
 * data trade-off that Gemini carries.
 */
class CloudflareProvider extends HttpProvider
{
    public function configured(): bool
    {
        return $this->setting('key') !== '' && $this->setting('account') !== '';
    }

    public function text(string $input, ?string $instructions, int $maxOutputTokens): array
    {
        $url = rtrim($this->setting('base_url'), '/').'/accounts/'.$this->setting('account').'/ai/run/'.$this->model();
        $messages = [];
        if (filled($instructions)) {
            $messages[] = ['role' => 'system', 'content' => $instructions];
        }
        $messages[] = ['role' => 'user', 'content' => $input];

        $response = $this->request()
            ->withToken($this->setting('key'))
            ->post($url, ['messages' => $messages, 'max_tokens' => $maxOutputTokens]);

        if (! $response->successful()) {
            $this->fail('HTTP '.$response->status(), $response->status());
        }
        if ($response->json('success') === false) {
            $this->fail('api errors='.json_encode($response->json('errors', []), JSON_UNESCAPED_UNICODE));
        }

        $text = $this->answerText($response->json('result'));
        if ($text === '') {
            $this->fail('empty answer in result.response and result.choices');
        }

        return [
            'text' => $text,
            'input_tokens' => (int) $response->json('result.usage.prompt_tokens', 0),
            'output_tokens' => (int) $response->json('result.usage.completion_tokens', 0),
            'model' => $this->model(),
        ];
    }

    /**
     * Workers AI puts the answer in `result.response` as a string for prose — but when the model replies with valid
     * JSON it hands back the ALREADY PARSED object there instead, and JSON is exactly what the simulated-market panel
     * asks every voice for (verified against the live API, 2026-11-10). Re-encoding keeps this method's contract (text
     * in, text out) instead of dropping a perfectly good answer; `result.choices` covers the OpenAI-shaped variant.
     */
    private function answerText(mixed $result): string
    {
        $response = is_array($result) ? ($result['response'] ?? null) : null;
        if (is_string($response)) {
            return trim($response);
        }
        if (is_array($response) && $response !== []) {
            return (string) json_encode($response, JSON_UNESCAPED_UNICODE);
        }
        $content = data_get($result, 'choices.0.message.content');

        return is_string($content) ? trim($content) : '';
    }
}
