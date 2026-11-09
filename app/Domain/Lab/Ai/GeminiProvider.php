<?php

namespace App\Domain\Lab\Ai;

/**
 * Google Gemini (generateContent). First in the free-tier chain: the most generous free allowance and the
 * closest Arabic marketing quality to the paid frontier. Its free tier may train on submitted input, so the
 * config marks it accordingly and the gateway keeps sensitive runs away from it.
 */
class GeminiProvider extends HttpProvider
{
    public function configured(): bool
    {
        return $this->setting('key') !== '';
    }

    public function text(string $input, ?string $instructions, int $maxOutputTokens): array
    {
        $url = rtrim($this->setting('base_url'), '/').'/models/'.$this->model().':generateContent';
        $payload = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $input]]]],
            'generationConfig' => ['maxOutputTokens' => $maxOutputTokens, 'temperature' => 0.2],
        ];
        if (filled($instructions)) {
            $payload['systemInstruction'] = ['parts' => [['text' => $instructions]]];
        }

        $response = $this->request()->withHeaders(['x-goog-api-key' => $this->setting('key')])->post($url, $payload);

        if (! $response->successful()) {
            $this->fail('HTTP '.$response->status(), $response->status());
        }
        if ($blocked = $response->json('promptFeedback.blockReason')) {
            $this->fail('blocked='.(string) $blocked);
        }

        $text = collect($response->json('candidates.0.content.parts', []))
            ->pluck('text')
            ->filter(fn ($part) => is_string($part) && trim($part) !== '')
            ->implode('');
        if (trim($text) === '') {
            $this->fail('no text in first candidate');
        }

        return [
            'text' => trim($text),
            'input_tokens' => (int) $response->json('usageMetadata.promptTokenCount', 0),
            'output_tokens' => (int) $response->json('usageMetadata.candidatesTokenCount', 0),
            'model' => $this->model(),
        ];
    }
}
