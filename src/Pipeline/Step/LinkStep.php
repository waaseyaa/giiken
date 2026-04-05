<?php
declare(strict_types=1);
namespace Giiken\Pipeline\Step;

use Giiken\Pipeline\CompilationPayload;
use Giiken\Pipeline\Provider\EmbeddingProviderInterface;
use Waaseyaa\AI\Pipeline\PipelineContext;
use Waaseyaa\AI\Pipeline\PipelineStepInterface;
use Waaseyaa\AI\Pipeline\StepResult;

final class LinkStep implements PipelineStepInterface
{
    private const SIMILARITY_THRESHOLD = 0.75;
    private const MAX_LINKS = 5;

    public function __construct(
        private readonly EmbeddingProviderInterface $embeddings,
    ) {}

    public function process(array $input, PipelineContext $context): StepResult
    {
        $payload = $input['payload'];
        $query = implode(' ', array_filter([
            $payload->title,
            $payload->summary,
            implode(' ', $payload->topics),
        ]));

        if (trim($query) === '') {
            return StepResult::success($input);
        }

        $results = $this->embeddings->search($query, $payload->communityId, self::MAX_LINKS + 5);
        $linked = [];

        foreach ($results as $result) {
            if ($result['score'] < self::SIMILARITY_THRESHOLD) {
                continue;
            }
            $linked[] = $result['id'];
            if (count($linked) >= self::MAX_LINKS) {
                break;
            }
        }

        $payload->linkedItemIds = $linked;
        return StepResult::success($input);
    }

    public function describe(): string
    {
        return 'Link to related KnowledgeItems via semantic similarity';
    }
}
