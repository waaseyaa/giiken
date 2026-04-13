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
