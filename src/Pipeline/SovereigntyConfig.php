<?php

declare(strict_types=1);

namespace App\Pipeline;

final class SovereigntyConfig
{
    /** @param array<string, string> $config */
    public function __construct(
        private readonly array $config,
    ) {}

    public function get(string $key, string $default = ''): string
    {
        return $this->config[$key] ?? $default;
    }
}
