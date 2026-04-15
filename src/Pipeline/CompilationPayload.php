<?php

declare(strict_types=1);

namespace App\Pipeline;

use App\Entity\KnowledgeItem\AccessTier;
use App\Entity\KnowledgeItem\KnowledgeType;

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

    /**
     * Desired access tier for the resulting `KnowledgeItem`. Defaults to
     * `AccessTier::Public` when not overridden by the caller.
     */
    public AccessTier $accessTier = AccessTier::Public;

    /**
     * When true, all steps run but `EmbedStep` skips `save()` and
     * `embeddings->store()`. `entityUuid` is still populated so callers
     * can inspect the would-be id.
     */
    public bool $dryRun = false;

    /**
     * UUID of the persisted `KnowledgeItem`. Written by `EmbedStep` before
     * save so callers can surface it without a follow-up query.
     */
    public ?string $entityUuid = null;
}
