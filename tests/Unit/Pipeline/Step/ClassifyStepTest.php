<?php
declare(strict_types=1);
namespace Giiken\Tests\Unit\Pipeline\Step;

use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Pipeline\CompilationPayload;
use Giiken\Pipeline\PipelineException;
use Giiken\Pipeline\Provider\LlmProviderInterface;
use Giiken\Pipeline\Step\ClassifyStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AiPipeline\PipelineContext;

#[CoversClass(ClassifyStep::class)]
final class ClassifyStepTest extends TestCase
{
    #[Test]
    public function it_classifies_governance_content(): void
    {
        $llm = $this->createLlmProvider('governance');
        $step = new ClassifyStep($llm);
        $payload = new CompilationPayload();
        $payload->markdownContent = '# Council Meeting Minutes';

        $context = new PipelineContext(['payload' => $payload]);
        $result = $step->process(['payload' => $payload], $context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(KnowledgeType::Governance, $payload->knowledgeType);
    }

    #[Test]
    public function it_classifies_land_content(): void
    {
        $llm = $this->createLlmProvider('land');
        $step = new ClassifyStep($llm);
        $payload = new CompilationPayload();
        $payload->markdownContent = '# Environmental Assessment';

        $context = new PipelineContext(['payload' => $payload]);
        $step->process(['payload' => $payload], $context);

        $this->assertSame(KnowledgeType::Land, $payload->knowledgeType);
    }

    #[Test]
    public function it_retries_on_invalid_response_then_throws(): void
    {
        $llm = new class implements LlmProviderInterface {
            public function complete(string $systemPrompt, string $userPrompt): string
            {
                return 'nonsense_type';
            }
        };

        $step = new ClassifyStep($llm);
        $payload = new CompilationPayload();
        $payload->markdownContent = 'Some content';
        $context = new PipelineContext(['payload' => $payload]);

        $this->expectException(PipelineException::class);
        $this->expectExceptionMessage('ClassifyStep');
        $step->process(['payload' => $payload], $context);
    }

    private function createLlmProvider(string $response): LlmProviderInterface
    {
        return new class($response) implements LlmProviderInterface {
            public function __construct(private readonly string $response) {}
            public function complete(string $systemPrompt, string $userPrompt): string { return $this->response; }
        };
    }
}
