<?php
declare(strict_types=1);
namespace App\Pipeline\Step;

use App\Entity\KnowledgeItem\Source\Attribution;
use App\Entity\KnowledgeItem\Source\CopyrightStatus;
use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Entity\KnowledgeItem\Source\KnowledgeItemSource;
use App\Entity\KnowledgeItem\Source\OriginType;
use App\Entity\KnowledgeItem\Source\Rights;
use App\Entity\KnowledgeItem\Source\SourceOrigin;
use App\Entity\KnowledgeItem\Source\SourceReference;
use App\Pipeline\Provider\EmbeddingProviderInterface;
use Waaseyaa\AI\Pipeline\PipelineContext;
use Waaseyaa\AI\Pipeline\PipelineStepInterface;
use Waaseyaa\AI\Pipeline\StepResult;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

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
        $payload->entityUuid = $entityId;

        $item = KnowledgeItem::make([
            'uuid'             => $entityId,
            'community_id'     => $payload->communityId,
            'title'            => $payload->title,
            'content'          => $payload->content,
            'knowledge_type'   => $payload->knowledgeType?->value,
            'access_tier'      => $payload->accessTier->value,
            'source_media_ids' => json_encode([$payload->mediaId], JSON_THROW_ON_ERROR),
            'compiled_at'      => date('c'),
        ]);

        $item->setSource(new KnowledgeItemSource(
            origin: new SourceOrigin(
                type: OriginType::Upload,
                ingestedAt: date('c'),
                system: 'giiken-ingest-file',
                pipelineVersion: '0.1.0',
            ),
            reference: new SourceReference(
                url: $payload->sourceUrl,
                sourceName: $payload->sourceUrl !== null ? 'external-reference' : 'local-upload',
                externalId: $payload->mediaId,
                contentType: $payload->mimeType,
            ),
            attribution: new Attribution(),
            rights: new Rights(
                copyrightStatus: CopyrightStatus::ExternalLink,
                consentPublic: true,
                consentAiTraining: false,
            ),
        ));

        if ($payload->dryRun) {
            return StepResult::success($input);
        }

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
