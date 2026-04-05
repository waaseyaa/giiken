<?php
declare(strict_types=1);
namespace Giiken\Pipeline\Step;

use Giiken\Pipeline\CompilationPayload;
use Giiken\Pipeline\PipelineException;
use Giiken\Pipeline\Provider\LlmProviderInterface;
use Waaseyaa\AiPipeline\PipelineContext;
use Waaseyaa\AiPipeline\PipelineStepInterface;
use Waaseyaa\AiPipeline\StepResult;

final class StructureStep implements PipelineStepInterface
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a knowledge structuring assistant. Given a document and its knowledge type,
extract structured metadata. Respond with ONLY valid JSON, no markdown fences.

Return this exact structure:
{
    "title": "A clear, descriptive title for this document",
    "summary": "A 1-3 sentence summary of the key content",
    "people": ["Person Name 1", "Person Name 2"],
    "places": ["Place Name 1"],
    "topics": ["topic1", "topic2"],
    "key_passages": ["Important quote or passage from the text"]
}

All arrays can be empty if not applicable. The title should be descriptive, not generic.
PROMPT;

    public function __construct(
        private readonly LlmProviderInterface $llm,
    ) {}

    public function process(array $input, PipelineContext $context): StepResult
    {
        $payload = $input['payload'];
        $typeHint = $payload->knowledgeType !== null
            ? "Knowledge type: {$payload->knowledgeType->value}"
            : '';

        $userPrompt = "{$typeHint}\n\n{$payload->markdownContent}";
        $response = $this->llm->complete(self::SYSTEM_PROMPT, $userPrompt);

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw PipelineException::fromStep('StructureStep', $e);
        }

        $payload->title = (string) ($data['title'] ?? '');
        $payload->summary = (string) ($data['summary'] ?? '');
        $payload->people = array_map('strval', (array) ($data['people'] ?? []));
        $payload->places = array_map('strval', (array) ($data['places'] ?? []));
        $payload->topics = array_map('strval', (array) ($data['topics'] ?? []));
        $payload->keyPassages = array_map('strval', (array) ($data['key_passages'] ?? []));
        $payload->content = $payload->markdownContent;

        return StepResult::success($input);
    }

    public function describe(): string
    {
        return 'Extract structured metadata from content via LLM';
    }
}
