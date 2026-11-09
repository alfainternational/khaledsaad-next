<?php

namespace App\Domain\Lab\Ai;

/**
 * Any vendor speaking the OpenAI chat/completions dialect — Groq, Mistral, Cerebras, OpenRouter, GitHub Models.
 * One driver covers them all, so adding a free tier to the chain is a config entry rather than a class.
 */
class OpenAiCompatibleProvider extends HttpProvider
{
    public function configured(): bool
    {
        return $this->setting('key') !== '' && $this->setting('base_url') !== '';
    }

    public function text(string $input, ?string $instructions, int $maxOutputTokens): array
    {
        $messages = [];
        if (filled($instructions)) {
            $messages[] = ['role' => 'system', 'content' => $instructions];
        }
        $messages[] = ['role' => 'user', 'content' => $input];

        $response = $this->request()
            ->withToken($this->setting('key'))
            ->post(rtrim($this->setting('base_url'), '/').'/chat/completions', [
                'model' => $this->model(),
                'messages' => $messages,
                'max_tokens' => $maxOutputTokens,
                'temperature' => 0.2,
            ]);

        if (! $response->successful()) {
            $this->fail('HTTP '.$response->status(), $response->status());
        }

        $text = $response->json('choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            $this->fail('empty first choice');
        }

        return [
            'text' => trim($text),
            'input_tokens' => (int) $response->json('usage.prompt_tokens', 0),
            'output_tokens' => (int) $response->json('usage.completion_tokens', 0),
            'model' => $this->model(),
        ];
    }
}
