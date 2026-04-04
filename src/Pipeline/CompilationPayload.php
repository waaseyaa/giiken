<?php

declare(strict_types=1);

namespace Giiken\Pipeline;

use Giiken\Entity\KnowledgeItem\KnowledgeType;

final class CompilationPayload
{
    public string $markdownContent = '';
    public string $mimeType = '';
    public string $mediaId = '';
    public string $communityId = '';
    public ?KnowledgeType $knowledgeType = null;
    public string $title = '';
    public string $content = '';
    /** @var string[] */
    public array $people = [];
    /** @var string[] */
    public array $places = [];
    /** @var string[] */
    public array $topics = [];
    public string $summary = '';
    /** @var string[] */
    public array $keyPassages = [];
    /** @var string[] */
    public array $linkedItemIds = [];
    public ?string $sourceUrl = null;
}
