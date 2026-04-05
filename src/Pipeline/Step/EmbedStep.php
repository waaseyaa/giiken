<?php
declare(strict_types=1);
namespace Giiken\Pipeline\Step;

use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Pipeline\Provider\EmbeddingProviderInterface;
use Waaseyaa\AiPipeline\PipelineContext;
use Waaseyaa\AiPipeline\PipelineStepInterface;
use Waaseyaa\AiPipeline\StepResult;
use Waaseyaa\Entity\EntityRepositoryInterface;

final class EmbedStep implements PipelineStepInterface
{
    public function __construct(
        private readonly EmbeddingProviderInterface $embeddings,
        private readonly EntityRepositoryInterface $repository,
    ) {}

    public function process(array $input, PipelineContext $context): StepResult
    {
        $payload = $input['payload'];
        $entityId = bin2hex(random_bytes(16));

        $item = new KnowledgeItem([
            'uuid'             => $entityId,
            'community_id'     => $payload->communityId,
            'title'            => $payload->title,
            'content'          => $payload->content,
            'knowledge_type'   => $payload->knowledgeType?->value,
            'access_tier'      => 'public',
            'source_media_ids' => json_encode([$payload->mediaId], JSON_THROW_ON_ERROR),
            'compiled_at'      => date('c'),
        ]);

        $this->repository->save($item);

        $embeddingText = $item->toMarkdown();
        $this->embeddings->store($entityId, $embeddingText, $payload->communityId);

        return StepResult::success($input);
    }

    public function describe(): string
    {
        return 'Generate vector embedding and persist KnowledgeItem';
    }
}
