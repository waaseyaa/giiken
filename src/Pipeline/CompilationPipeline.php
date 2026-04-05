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
use Waaseyaa\AiPipeline\PipelineContext;
use Waaseyaa\AiPipeline\PipelineStepInterface;
use Waaseyaa\Entity\EntityRepositoryInterface;

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
        $context = new PipelineContext(['payload' => $payload]);
        $input = ['payload' => $payload];

        foreach ($steps as $step) {
            $result = $step->process($input, $context);
            if (!$result->isSuccess()) {
                throw PipelineException::fromStep(
                    $step->describe(),
                    new \RuntimeException('Step returned failure'),
                );
            }
            $input = $result->getOutput();
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
