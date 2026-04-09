<?php

declare(strict_types=1);

namespace Giiken\Pipeline\Provider;

/**
 * Safe default LLM for local dev when no API keys or Ollama are configured.
 */
final class NullLlmProvider implements LlmProviderInterface
{
    public function complete(string $systemPrompt, string $userPrompt): string
    {
        return 'Configure a real LLM provider for production Q&A. '
            . 'Stub response: see context lines above for [item-…] citations.';
    }
}
