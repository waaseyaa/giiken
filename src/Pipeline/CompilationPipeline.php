<?php
declare(strict_types=1);
namespace App\Pipeline;

use App\Entity\KnowledgeItem\AccessTier;
use App\Entity\KnowledgeItem\KnowledgeType;
use App\Ingestion\RawDocument;
use App\Pipeline\Provider\EmbeddingProviderInterface;
use App\Pipeline\Provider\LlmProviderInterface;
use App\Pipeline\Step\ClassifyStep;
use App\Pipeline\Step\EmbedStep;
use App\Pipeline\Step\LinkStep;
use App\Pipeline\Step\StructureStep;
use App\Pipeline\Step\TranscribeStep;
use Waaseyaa\AI\Pipeline\PipelineContext;
use Waaseyaa\AI\Pipeline\PipelineStepInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

final class CompilationPipeline
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
        private readonly EmbeddingProviderInterface $embeddings,
        private readonly EntityRepositoryInterface $repository,
    ) {}

    /**
     * Run the 5-step compilation pipeline and return the populated payload.
     *
     * @param AccessTier|null   $accessTier   Override persisted `access_tier`;
     *                                        defaults to `AccessTier::Public`.
     * @param KnowledgeType|null $forcedType  Skip `ClassifyStep`'s LLM call
     *                                        and force this type instead.
     * @param bool              $dryRun       When true, run every step but do
     *                                        not persist the `KnowledgeItem`
     *                                        or store its embedding.
     */
    public function compile(
        RawDocument $document,
        string $communityId,
        ?AccessTier $accessTier = null,
        ?KnowledgeType $forcedType = null,
        bool $dryRun = false,
    ): CompilationPayload {
        $payload = new CompilationPayload();
        $payload->markdownContent = $document->markdownContent;
        $payload->mimeType = $document->mimeType;
        $payload->mediaId = $document->mediaId;
        $payload->communityId = $communityId;
        $payload->sourceUrl = $document->metadata['frontmatter']['source'] ?? null;
        $payload->accessTier = $accessTier ?? AccessTier::Public;
        $payload->knowledgeType = $forcedType;
        $payload->dryRun = $dryRun;

        $steps = $this->buildSteps();
        $context = new PipelineContext(pipelineId: 'compilation', startedAt: time());
        $input = ['payload' => $payload];

        foreach ($steps as $step) {
            $result = $step->process($input, $context);
            if (!$result->success) {
                throw PipelineException::fromStep(
                    $step->describe(),
                    new \RuntimeException($result->message ?: 'Step returned failure'),
                );
            }
            if ($result->stopPipeline) {
                break;
            }
            $input = $result->output;
        }

        return $payload;
    }

    /** @return PipelineStepInterface[] */
    private function buildSteps(): array
    {
        return [
            new TranscribeStep(),
            new ClassifyStep($this->llm),
            new StructureStep($this->llm),
            new LinkStep($this->embeddings),
            new EmbedStep($this->embeddings, $this->repository),
        ];
    }
}
