<?php
declare(strict_types=1);
namespace Giiken\Tests\Unit\Pipeline\Step;

use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Pipeline\CompilationPayload;
use Giiken\Pipeline\PipelineException;
use Giiken\Pipeline\Provider\LlmProviderInterface;
use Giiken\Pipeline\Step\StructureStep;
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
    public function it_throws_on_invalid_json(): void
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
