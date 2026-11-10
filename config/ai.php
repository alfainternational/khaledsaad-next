<?php

/*
|--------------------------------------------------------------------------
| AI providers (plan 2026-11-09, zero-cost revision §ب)
|--------------------------------------------------------------------------
|
| The platform calls a CHAIN, not a provider. AiGateway walks `chain` in order and uses the first entry that is
| configured and still inside its rate allowance, so free tiers can be stacked into one dependable service: when
| one is spent (HTTP 429) the next answers instead of the run failing.
|
| Two rules the code enforces rather than the prompt:
|   - `trains_on_input` marks a vendor whose terms allow training on what we send. Runs carrying a client's own
|     business data (AiContext::$sensitive) skip those providers entirely.
|   - `max_output_tokens` and the per-provider `rate` caps live here. A model never gets to decide its own limit.
|
| A `*_config` key reads its value from another config path at resolve time. OpenAI therefore keeps reading
| config('services.openai.*'), which stays the single source for that key.
|
*/

return [

    // Attempt order. Override per environment with AI_CHAIN, e.g. "cloudflare,openai".
    'chain' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AI_CHAIN', 'gemini,cloudflare,groq,openai'))
    ))),

    // Provider used for runs marked sensitive when it is configured; must not train on input.
    'sensitive_provider' => env('AI_SENSITIVE_PROVIDER', 'cloudflare'),

    'default_instructions' => 'أنت مساعد تسويق عربي دقيق. افصل الحقائق عن الافتراضات، ولا تخترع أرقاماً أو مصادر.',

    'limits' => [
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 4000),
        'timeout' => (int) env('AI_TIMEOUT', 60),
        'retries' => (int) env('AI_RETRIES', 2),
    ],

    /*
    | Scarcity on a free stack is requests and tokens, not dollars. `cost_micro_usd` is still recorded on every
    | run (zero while the chain is free) so the same ledger answers the question the day a paid key is switched on.
    */
    'quota' => [
        'workspace_monthly_requests' => (int) env('AI_WORKSPACE_MONTHLY_REQUESTS', 400),
        'workspace_monthly_tokens' => (int) env('AI_WORKSPACE_MONTHLY_TOKENS', 2000000),
    ],

    /*
    | Identical prompt + chain signature returns the stored answer without spending allowance. Off by default so a
    | member who asks twice gets two real answers; the simulated market turns it on, where reproducibility matters.
    */
    'cache' => [
        'ttl_minutes' => (int) env('AI_CACHE_TTL_MINUTES', 0),
    ],

    /*
    | السوق المحاكى — the hard ceiling of one panel run, in config and not in the prompt. A panel is the only
    | feature that calls the model more than once per run (voices × rounds), so its bound is the difference
    | between a bounded feature and an open loop: 5 × 2 = 10 calls, each capped well under the global token limit.
    | `cache_ttl_minutes` makes the same panel reproducible for a day — what an audit and a fair A/B both need.
    */
    'panel' => [
        'max_voices' => (int) env('AI_PANEL_MAX_VOICES', 5),
        'max_rounds' => (int) env('AI_PANEL_MAX_ROUNDS', 2),
        'max_output_tokens' => (int) env('AI_PANEL_MAX_OUTPUT_TOKENS', 600),
        'cache_ttl_minutes' => (int) env('AI_PANEL_CACHE_TTL_MINUTES', 1440),
    ],

    'providers' => [

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            // Google's free tier may use submitted content to improve its products.
            'trains_on_input' => (bool) env('GEMINI_TRAINS_ON_INPUT', true),
            'rate' => ['per_minute' => (int) env('GEMINI_RPM', 10), 'per_day' => (int) env('GEMINI_RPD', 200)],
        ],

        'cloudflare' => [
            'driver' => 'cloudflare',
            'key' => env('CLOUDFLARE_AI_TOKEN'),
            'account' => env('CLOUDFLARE_ACCOUNT_ID'),
            'base_url' => env('CLOUDFLARE_AI_BASE_URL', 'https://api.cloudflare.com/client/v4'),
            'model' => env('CLOUDFLARE_AI_MODEL', '@cf/meta/llama-3.3-70b-instruct-fp8-fast'),
            'trains_on_input' => false,
            'rate' => ['per_minute' => (int) env('CLOUDFLARE_AI_RPM', 30), 'per_day' => (int) env('CLOUDFLARE_AI_RPD', 0)],
        ],

        'groq' => [
            'driver' => 'openai_compatible',
            'key' => env('GROQ_API_KEY'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
            'trains_on_input' => false,
            'rate' => ['per_minute' => (int) env('GROQ_RPM', 30), 'per_day' => (int) env('GROQ_RPD', 0)],
        ],

        // Paid, and last on purpose: ready to switch on from revenue, idle until then.
        'openai' => [
            'driver' => 'openai',
            'key_config' => 'services.openai.key',
            'base_url_config' => 'services.openai.base_url',
            'model_config' => 'services.openai.model',
            'trains_on_input' => false,
            'rate' => ['per_minute' => (int) env('OPENAI_RPM', 0), 'per_day' => (int) env('OPENAI_RPD', 0)],
        ],

    ],

];
