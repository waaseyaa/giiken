<?php

declare(strict_types=1);

namespace Giiken\Pipeline\Provider;

interface LlmProviderInterface
{
    public function complete(string $systemPrompt, string $userPrompt): string;
}
