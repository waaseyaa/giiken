<?php

declare(strict_types=1);

namespace App\Http\Api\Ask;

final readonly class AskRequestData
{
    public function __construct(
        public string $communitySlug,
        public string $question,
    ) {}
}
