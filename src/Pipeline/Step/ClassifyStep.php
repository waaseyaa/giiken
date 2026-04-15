<?php
declare(strict_types=1);
namespace App\Pipeline\Step;

use App\Entity\KnowledgeItem\KnowledgeType;
use App\Pipeline\PipelineException;
use App\Pipeline\Provider\LlmProviderInterface;
use Waaseyaa\AI\Pipeline\PipelineContext;
use Waaseyaa\AI\Pipeline\PipelineStepInterface;
use Waaseyaa\AI\Pipeline\StepResult;

final class ClassifyStep implements PipelineStepInterface
{
    private const MAX_RETRIES = 2;
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a knowledge classifier. Given a document, classify it into exactly one category.
Respond with ONLY the category name, nothing else.

Categories:
- cultural (oral histories, teachings, ceremonies, language, elder interviews)
- governance (council resolutions, meeting minutes, policies, treaties, funding)
- land (environmental monitoring, land use, resource assessments, territory knowledge)
- relationship (people, organizations, contacts and their roles)
- event (dated occurrences, meetings, ceremonies, milestones)
PROMPT;

    public function __construct(
        private readonly LlmProviderInterface $llm,
    ) {}

    public function process(array $input, PipelineContext $context): StepResult
    {
        $payload = $input['payload'];

        // Caller forced a type (CLI `--type`, Management UI override, etc.);
        // skip the LLM round-trip entirely.
        if ($payload->knowledgeType !== null) {
            return StepResult::success($input);
        }

        $response = '';

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $response = strtolower(trim($this->llm->complete(
                self::SYSTEM_PROMPT,
                $payload->markdownContent,
            )));

            $type = KnowledgeType::tryFrom($response);
            if ($type !== null) {
                $payload->knowledgeType = $type;
                return StepResult::success($input);
            }
        }

        throw PipelineException::fromStep(
            'ClassifyStep',
            new \RuntimeException("LLM returned invalid knowledge type after {$attempt} attempts: '{$response}'"),
        );
    }

    public function describe(): string
    {
        return 'Classify content into knowledge type via LLM';
    }
}
