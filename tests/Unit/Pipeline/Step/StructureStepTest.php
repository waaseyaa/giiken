<?php
declare(strict_types=1);
namespace App\Tests\Unit\Pipeline\Step;

use App\Entity\KnowledgeItem\KnowledgeType;
use App\Pipeline\CompilationPayload;
use App\Pipeline\PipelineException;
use App\Pipeline\Provider\LlmProviderInterface;
use App\Pipeline\Step\StructureStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Pipeline\PipelineContext;

#[CoversClass(StructureStep::class)]
final class StructureStepTest extends TestCase
{
    #[Test]
    public function it_extracts_structured_fields(): void
    {
        $llmResponse = json_encode([
            'title' => 'Council Meeting — Solar Project Discussion',
            'summary' => 'Council debated the proposed 5MW solar installation.',
            'people' => ['Mayor Smith', 'Councillor Jones'],
            'places' => ['Massey', 'Highway 17 corridor'],
            'topics' => ['solar energy', 'land use', 'council vote'],
            'key_passages' => ['The motion passed 4-1 in favour of proceeding.'],
        ], JSON_THROW_ON_ERROR);

        $llm = new class($llmResponse) implements LlmProviderInterface {
            public function __construct(private readonly string $r) {}
            public function complete(string $s, string $u): string { return $this->r; }
        };

        $step = new StructureStep($llm);
        $payload = new CompilationPayload();
        $payload->markdownContent = '# Council meeting content...';
        $payload->knowledgeType = KnowledgeType::Governance;

        $context = new PipelineContext(pipelineId: 'test', startedAt: time());
        $result = $step->process(['payload' => $payload], $context);

        $this->assertTrue($result->success);
        $this->assertSame('Council Meeting — Solar Project Discussion', $payload->title);
        $this->assertSame(['Mayor Smith', 'Councillor Jones'], $payload->people);
        $this->assertSame(['Massey', 'Highway 17 corridor'], $payload->places);
        $this->assertStringContainsString('Council meeting content', $payload->content);
    }

    #[Test]
    public function it_retries_on_invalid_json_then_succeeds(): void
    {
        $validJson = json_encode([
            'title' => 'Recovered',
            'summary' => 'After retry',
            'people' => [],
            'places' => [],
            'topics' => [],
            'key_passages' => [],
        ], JSON_THROW_ON_ERROR);

        $llm = new class($validJson) implements LlmProviderInterface {
            private int $calls = 0;
            public function __construct(private readonly string $valid) {}
            public function complete(string $s, string $u): string
            {
                return ++$this->calls === 1 ? 'not json' : $this->valid;
            }
        };

        $step = new StructureStep($llm);
        $payload = new CompilationPayload();
        $payload->markdownContent = 'Content';
        $payload->knowledgeType = KnowledgeType::Cultural;
        $context = new PipelineContext(pipelineId: 'test', startedAt: time());

        $result = $step->process(['payload' => $payload], $context);

        $this->assertTrue($result->success);
        $this->assertSame('Recovered', $payload->title);
    }

    #[Test]
    public function it_throws_after_exhausting_retries(): void
    {
        $llm = new class implements LlmProviderInterface {
            public function complete(string $s, string $u): string { return 'not json'; }
        };

        $step = new StructureStep($llm);
        $payload = new CompilationPayload();
        $payload->markdownContent = 'Content';
        $payload->knowledgeType = KnowledgeType::Cultural;
        $context = new PipelineContext(pipelineId: 'test', startedAt: time());

        $this->expectException(PipelineException::class);
        $this->expectExceptionMessage('StructureStep');
        $step->process(['payload' => $payload], $context);
    }
}
