<?php

declare(strict_types=1);

namespace Giiken\Export;

final readonly class ImportResult
{
    /**
     * @param string[] $warnings
     */
    public function __construct(
        public string $communityId,
        public int $itemsImported,
        public int $mediaLinked,
        public array $warnings,
    ) {}
}
