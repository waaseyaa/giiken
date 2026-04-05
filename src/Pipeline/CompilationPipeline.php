<?php
declare(strict_types=1);
namespace Giiken\Pipeline;

use Giiken\Ingestion\RawDocument;
use Giiken\Pipeline\Provider\EmbeddingProviderInterface;
use Giiken\Pipeline\Provider\LlmProviderInterface;
use Giiken\Pipeline\Step\ClassifyStep;
use Giiken\Pipeline\Step\EmbedStep;
use Giiken\Pipeline\Step\LinkStep;
use Giiken\Pipeline\Step\StructureStep;
use Giiken\Pipeline\Step\TranscribeStep;
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

    public function compile(RawDocument $document, string $communityId): void
    {
        $payload = new CompilationPayload();
        $payload->markdownContent = $document->markdownContent;
        $payload->mimeType = $document->mimeType;
        $payload->mediaId = $document->mediaId;
        $payload->communityId = $communityId;
        $payload->sourceUrl = $document->metadata['frontmatter']['source'] ?? null;

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
