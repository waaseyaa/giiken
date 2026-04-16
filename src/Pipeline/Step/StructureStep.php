<?php
declare(strict_types=1);
namespace App\Pipeline\Step;

use App\Pipeline\CompilationPayload;
use App\Pipeline\PipelineException;
use App\Pipeline\Provider\LlmProviderInterface;
use Waaseyaa\AI\Pipeline\PipelineContext;
use Waaseyaa\AI\Pipeline\PipelineStepInterface;
use Waaseyaa\AI\Pipeline\StepResult;

final class StructureStep implements PipelineStepInterface
{
    private const MAX_RETRIES = 2;
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
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $response = $this->llm->complete(self::SYSTEM_PROMPT, $userPrompt);

            try {
                $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $lastException = $e;
                continue;
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

        // Degrade gracefully when the model returns malformed JSON. This keeps
        // ingestion usable in local/low-config environments while still
        // preserving the source content for later curation.
        $payload->title = $payload->title !== ''
            ? $payload->title
            : $this->fallbackTitle($payload->markdownContent);
        $payload->summary = $payload->summary !== ''
            ? $payload->summary
            : $this->fallbackSummary($payload->markdownContent);
        $payload->people ??= [];
        $payload->places ??= [];
        $payload->topics ??= [];
        $payload->keyPassages ??= [];
        $payload->content = $payload->markdownContent;

        return StepResult::success($input);
    }

    public function describe(): string
    {
        return 'Extract structured metadata from content via LLM';
    }

    private function fallbackTitle(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $line = ltrim($line, '# ');
            if ($line !== '') {
                return mb_substr($line, 0, 180);
            }
        }

        return 'Untitled document';
    }

    private function fallbackSummary(string $markdown): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $markdown) ?? '');
        if ($normalized === '') {
            return 'No summary available.';
        }

        return mb_substr($normalized, 0, 320);
    }
}
