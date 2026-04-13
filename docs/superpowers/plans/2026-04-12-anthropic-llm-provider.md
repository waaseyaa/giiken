# Anthropic LLM Provider Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire up the Anthropic Messages API as Giiken's first real LLM provider so Q&A returns actual answers instead of a stub string.

**Architecture:** A thin adapter (`AnthropicLlmProvider`) accepts `Waaseyaa\AI\Agent\Provider\ProviderInterface` (the mockable interface that `AnthropicProvider` implements) and adapts it to `App\Pipeline\Provider\LlmProviderInterface`. The adapter is bound conditionally in `AppServiceProvider` based on `WAASEYAA_LLM_PROVIDER` and `ANTHROPIC_API_KEY` env vars, falling back to `NullLlmProvider` when unconfigured.

**Tech Stack:** PHP 8.4, Waaseyaa framework (`waaseyaa/ai-agent`), PHPUnit 10.5

---

## File Map

| Action | File | Responsibility |
|--------|------|---------------|
| Create | `src/Pipeline/Provider/AnthropicLlmProvider.php` | Adapter: translates `LlmProviderInterface::complete()` to `ProviderInterface::sendMessage()` |
| Create | `tests/Unit/Pipeline/Provider/AnthropicLlmProviderTest.php` | Unit tests for the adapter |
| Modify | `src/Provider/AppServiceProvider.php:130` | Conditional provider wiring based on env |
| Modify | `config/waaseyaa.php:75-84` | Add `llm_provider`, `anthropic_api_key`, `anthropic_model` keys |
| Modify | `.env.example` | Add env var stubs |
| Modify | `docs/architecture/lifecycle.md` (section 3.3) | Document provider indirection |

---

### Task 1: AnthropicLlmProvider adapter with tests

**Files:**
- Create: `tests/Unit/Pipeline/Provider/AnthropicLlmProviderTest.php`
- Create: `src/Pipeline/Provider/AnthropicLlmProvider.php`

- [ ] **Step 1: Write the failing test for successful completion**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Pipeline\Provider;

use App\Pipeline\Provider\AnthropicLlmProvider;
use App\Pipeline\Provider\LlmProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Provider\MessageRequest;
use Waaseyaa\AI\Agent\Provider\MessageResponse;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;

#[CoversClass(AnthropicLlmProvider::class)]
final class AnthropicLlmProviderTest extends TestCase
{
    #[Test]
    public function completeReturnsTextFromResponse(): void
    {
        $anthropic = $this->createMock(ProviderInterface::class);
        $anthropic->expects($this->once())
            ->method('sendMessage')
            ->with($this->callback(function (MessageRequest $req): bool {
                $this->assertSame('You are helpful.', $req->system);
                $this->assertSame('What is 2+2?', $req->messages[0]['content']);
                $this->assertSame('user', $req->messages[0]['role']);
                return true;
            }))
            ->willReturn(new MessageResponse(
                content: [['type' => 'text', 'text' => 'The answer is 4.']],
                stopReason: 'end_turn',
            ));

        $provider = new AnthropicLlmProvider($anthropic);

        $this->assertInstanceOf(LlmProviderInterface::class, $provider);
        $this->assertSame('The answer is 4.', $provider->complete('You are helpful.', 'What is 2+2?'));
    }

    #[Test]
    public function completeReturnsFallbackWhenNoTextBlock(): void
    {
        $anthropic = $this->createMock(ProviderInterface::class);
        $anthropic->method('sendMessage')
            ->willReturn(new MessageResponse(
                content: [],
                stopReason: 'end_turn',
            ));

        $provider = new AnthropicLlmProvider($anthropic);

        $this->assertSame(
            'The LLM returned no text content. Please try again.',
            $provider->complete('system', 'question'),
        );
    }

    #[Test]
    public function completePropagatesCurlException(): void
    {
        $anthropic = $this->createMock(ProviderInterface::class);
        $anthropic->method('sendMessage')
            ->willThrowException(new \RuntimeException('cURL error: Connection timed out'));

        $provider = new AnthropicLlmProvider($anthropic);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cURL error: Connection timed out');

        $provider->complete('system', 'question');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Pipeline/Provider/AnthropicLlmProviderTest.php`
Expected: FAIL with "Class AnthropicLlmProvider not found"

- [ ] **Step 3: Write the adapter implementation**

```php
<?php

declare(strict_types=1);

namespace App\Pipeline\Provider;

use Waaseyaa\AI\Agent\Provider\MessageRequest;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;

final class AnthropicLlmProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly ProviderInterface $provider,
    ) {}

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        $request = new MessageRequest(
            messages: [['role' => 'user', 'content' => $userPrompt]],
            system: $systemPrompt,
            maxTokens: 2048,
        );

        $response = $this->provider->sendMessage($request);
        $text = $response->getText();

        if ($text === '') {
            return 'The LLM returned no text content. Please try again.';
        }

        return $text;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/Pipeline/Provider/AnthropicLlmProviderTest.php`
Expected: 3 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Pipeline/Provider/AnthropicLlmProvider.php tests/Unit/Pipeline/Provider/AnthropicLlmProviderTest.php
git commit -m "feat(#59): add AnthropicLlmProvider adapter with tests"
```

---

### Task 2: Configuration and conditional wiring

**Files:**
- Modify: `config/waaseyaa.php:75-84`
- Modify: `.env.example`
- Modify: `src/Provider/AppServiceProvider.php:130`

- [ ] **Step 1: Add LLM config keys to `config/waaseyaa.php`**

In the `'ai'` array (after line 80, the `openai_embedding_model` entry), add:

```php
'llm_provider' => getenv('WAASEYAA_LLM_PROVIDER') ?: '',
'anthropic_api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
'anthropic_model' => getenv('WAASEYAA_ANTHROPIC_MODEL') ?: 'claude-sonnet-4-6',
```

- [ ] **Step 2: Add env var stubs to `.env.example`**

Append to the file:

```
# LLM Provider (anthropic, or empty for stub)
WAASEYAA_LLM_PROVIDER=
ANTHROPIC_API_KEY=
WAASEYAA_ANTHROPIC_MODEL=claude-sonnet-4-6
```

- [ ] **Step 3: Update `AppServiceProvider` provider binding**

Replace line 130:

```php
$this->singleton(LlmProviderInterface::class, static fn (): LlmProviderInterface => new NullLlmProvider());
```

With:

```php
$this->singleton(LlmProviderInterface::class, static function (): LlmProviderInterface {
    $provider = getenv('WAASEYAA_LLM_PROVIDER') ?: '';
    $apiKey = getenv('ANTHROPIC_API_KEY') ?: '';

    if ($provider === 'anthropic' && $apiKey !== '') {
        $model = getenv('WAASEYAA_ANTHROPIC_MODEL') ?: 'claude-sonnet-4-6';

        return new AnthropicLlmProvider(
            new \Waaseyaa\AI\Agent\Provider\AnthropicProvider($apiKey, $model),
        );
    }

    return new NullLlmProvider();
});
```

Add the import at the top of the file if not already present:

```php
use App\Pipeline\Provider\AnthropicLlmProvider;
```

- [ ] **Step 4: Run the full test suite to verify nothing breaks**

Run: `./vendor/bin/phpunit`
Expected: 198+ tests, all PASS (NullLlmProvider still used in tests since env vars are unset)

- [ ] **Step 5: Commit**

```bash
git add config/waaseyaa.php .env.example src/Provider/AppServiceProvider.php
git commit -m "feat(#59): wire AnthropicLlmProvider behind env config with NullLlmProvider fallback"
```

---

### Task 3: Documentation update

**Files:**
- Modify: `docs/architecture/lifecycle.md` (section 3.3)

- [ ] **Step 1: Read current section 3.3 of lifecycle.md**

Run: `grep -n 'Q&A\|QaService\|LlmProvider\|## 3.3' docs/architecture/lifecycle.md` to find the relevant section.

- [ ] **Step 2: Add provider indirection paragraph**

Add a paragraph to section 3.3 (or create a new subsection if 3.3 does not cover Q&A) explaining:

```markdown
#### LLM Provider Indirection

`QaService` depends on `LlmProviderInterface::complete(string $systemPrompt, string $userPrompt): string`. The concrete implementation is resolved at boot time in `AppServiceProvider`:

- `WAASEYAA_LLM_PROVIDER=anthropic` + `ANTHROPIC_API_KEY` set: uses `AnthropicLlmProvider`, which delegates to the framework's `Waaseyaa\AI\Agent\Provider\AnthropicProvider` (cURL-based, Anthropic Messages API).
- Otherwise: falls back to `NullLlmProvider`, which returns a stub answer for local development.

The model defaults to `claude-sonnet-4-6` and can be overridden with `WAASEYAA_ANTHROPIC_MODEL`.
```

- [ ] **Step 3: Commit**

```bash
git add docs/architecture/lifecycle.md
git commit -m "docs(#59): document LLM provider indirection in lifecycle.md"
```

---

### Task 4: Smoke test with real API (manual, not CI)

This task is done locally with real credentials. It is NOT automated in CI.

- [ ] **Step 1: Set env vars in `.env`**

```
WAASEYAA_LLM_PROVIDER=anthropic
ANTHROPIC_API_KEY=<your-key-from-ansible-vault>
```

- [ ] **Step 2: Seed the test community (if not already seeded)**

Run: `./bin/waaseyaa giiken:seed:test-community`

- [ ] **Step 3: Start the dev server**

Run: `./bin/waaseyaa serve` (or `php -S 127.0.0.1:8080 -t public public/index.php`)

- [ ] **Step 4: Test Q&A endpoint**

Run: `curl 'http://127.0.0.1:8080/test-community/ask?q=What+knowledge+does+this+community+have'`

Expected: JSON response where `answer` is a substantive answer that references the seeded knowledge items (not the stub "Configure a real LLM provider" text). `citations` array should have entries. `noRelevantItems` should be `false`.

- [ ] **Step 5: Verify fallback still works**

Remove `ANTHROPIC_API_KEY` from `.env`, restart the server, and hit the same URL.

Expected: Response contains the stub text "Configure a real LLM provider for production Q&A."
