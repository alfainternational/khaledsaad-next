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

        $text = $response->json('result.response');
        if (! is_string($text) || trim($text) === '') {
            $this->fail('empty result.response');
        }

        return [
            'text' => trim($text),
            'input_tokens' => (int) $response->json('result.usage.prompt_tokens', 0),
            'output_tokens' => (int) $response->json('result.usage.completion_tokens', 0),
            'model' => $this->model(),
        ];
    }
}
