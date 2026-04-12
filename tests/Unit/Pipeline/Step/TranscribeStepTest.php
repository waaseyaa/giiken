<?php
declare(strict_types=1);
namespace App\Tests\Unit\Pipeline\Step;

use App\Pipeline\CompilationPayload;
use App\Pipeline\Step\TranscribeStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Pipeline\PipelineContext;

#[CoversClass(TranscribeStep::class)]
final class TranscribeStepTest extends TestCase
{
    #[Test]
    public function it_is_a_noop_when_markdown_content_exists(): void
    {
        $step = new TranscribeStep();
        $payload = new CompilationPayload();
        $payload->markdownContent = '# Existing Content';

        $context = new PipelineContext(pipelineId: 'test', startedAt: time());
        $result = $step->process(['payload' => $payload], $context);

        $this->assertTrue($result->success);
        $this->assertSame('# Existing Content', $payload->markdownContent);
    }

    #[Test]
    public function it_describes_itself(): void
    {
        $step = new TranscribeStep();
        $this->assertSame('Transcribe audio/video to text (passthrough for text)', $step->describe());
    }
}
