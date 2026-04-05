<?php
declare(strict_types=1);
namespace Giiken\Pipeline\Step;

use Waaseyaa\AiPipeline\PipelineContext;
use Waaseyaa\AiPipeline\PipelineStepInterface;
use Waaseyaa\AiPipeline\StepResult;

final class TranscribeStep implements PipelineStepInterface
{
    public function process(array $input, PipelineContext $context): StepResult
    {
        return StepResult::success($input);
    }

    public function describe(): string
    {
        return 'Transcribe audio/video to text (passthrough for text)';
    }
}
