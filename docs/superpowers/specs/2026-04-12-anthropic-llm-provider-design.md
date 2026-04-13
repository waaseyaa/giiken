# Anthropic LLM Provider for Q&A (Issue #59)

## Problem

`QaService` calls `LlmProviderInterface::complete()` which is bound to `NullLlmProvider`, returning a hardcoded stub string. The Q&A feature cannot be tested or used meaningfully.

## Approach

Wrap the existing `Waaseyaa\AI\Agent\Provider\AnthropicProvider` (cURL-based, already in `waaseyaa/ai-agent`) in a thin adapter that implements `App\Pipeline\Provider\LlmProviderInterface`. Wire it conditionally in `AppServiceProvider` based on env config, falling back to `NullLlmProvider` when no API key is set.

No new Composer dependencies required. The framework handles HTTP via cURL.

## Components

### 1. AnthropicLlmProvider adapter

**File:** `src/Pipeline/Provider/AnthropicLlmProvider.php`

Implements `LlmProviderInterface`. Constructor receives a `Waaseyaa\AI\Agent\Provider\AnthropicProvider` instance.

`complete(string $systemPrompt, string $userPrompt): string` does:
1. Build a `MessageRequest` with `system: $systemPrompt`, `messages: [['role' => 'user', 'content' => $userPrompt]]`, `maxTokens: 2048`.
2. Call `$this->provider->sendMessage($request)`.
3. Extract and return the text content from `MessageResponse::$content[0]['text']`.
4. If the response has no text content block, return a fallback message indicating no answer was generated.

### 2. Configuration

**File:** `config/waaseyaa.php` (modify `ai` section)

Add three keys:
```php
'llm_provider' => getenv('WAASEYAA_LLM_PROVIDER') ?: '',
'anthropic_api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
'anthropic_model' => getenv('WAASEYAA_ANTHROPIC_MODEL') ?: 'claude-sonnet-4-6',
```

**File:** `.env.example`

Add:
```
WAASEYAA_LLM_PROVIDER=
ANTHROPIC_API_KEY=
WAASEYAA_ANTHROPIC_MODEL=claude-sonnet-4-6
```

### 3. AppServiceProvider wiring

**File:** `src/Provider/AppServiceProvider.php` (modify line 130)

Replace the unconditional `NullLlmProvider` binding with:
```php
$this->singleton(LlmProviderInterface::class, function (): LlmProviderInterface {
    $config = $this->resolve(ConfigRepositoryInterface::class);
    $provider = $config->get('waaseyaa.ai.llm_provider', '');
    $apiKey = $config->get('waaseyaa.ai.anthropic_api_key', '');

    if ($provider === 'anthropic' && $apiKey !== '') {
        $model = $config->get('waaseyaa.ai.anthropic_model', 'claude-sonnet-4-6');
        return new AnthropicLlmProvider(
            new \Waaseyaa\AI\Agent\Provider\AnthropicProvider($apiKey, $model),
        );
    }

    return new NullLlmProvider();
});
```

### 4. Tests

**File:** `tests/Unit/Pipeline/Provider/AnthropicLlmProviderTest.php`

- Test `complete()` extracts text from a successful `MessageResponse`.
- Test `complete()` returns fallback when response has no text block.
- Test that cURL/API errors from the underlying provider propagate as `RuntimeException`.
- All tests use mocked `AnthropicProvider` (no live API calls).

### 5. Documentation

Update `docs/architecture/lifecycle.md` section 3.3 to mention the provider indirection and config switch.

## What does NOT change

- `QaService` is untouched (already decoupled via interface).
- `NullLlmProvider` stays as the safe default.
- `EmbeddingProviderInterface` / `NullEmbeddingProvider` are out of scope.
- No streaming needed for Q&A (the response is rendered server-side as an Inertia prop).

## Config for deployment

The Anthropic API key lives in the Ansible vault. The deployment playbook sets `ANTHROPIC_API_KEY` and `WAASEYAA_LLM_PROVIDER=anthropic` in the `.env` on the target host.

## Definition of done

- `/test-community/ask?q=` returns an answer that reflects the question and seeded items.
- `NullLlmProvider` still works when no key is configured.
- Unit tests green and hermetic (no live API calls).
- Lifecycle doc updated.
