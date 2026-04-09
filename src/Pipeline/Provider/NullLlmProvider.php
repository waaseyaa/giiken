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
        if (preg_match_all('/\[([a-zA-Z0-9_-]+)\]/', $userPrompt, $m) > 0) {
            $ids = array_values(array_unique($m[1]));
            if ($ids !== []) {
                $first = $ids[0];

                return 'Configure a real LLM provider for production Q&A. '
                    . "Stub answer citing the knowledge base [{$first}].";
            }
        }

        return 'Configure a real LLM provider for production Q&A. '
            . 'No bracketed item IDs were found in the stub context.';
    }
}
