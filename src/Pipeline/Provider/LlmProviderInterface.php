<?php

declare(strict_types=1);

namespace App\Pipeline\Provider;

interface LlmProviderInterface
{
    public function complete(string $systemPrompt, string $userPrompt): string;
}
