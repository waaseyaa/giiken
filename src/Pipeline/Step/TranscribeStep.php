<?php
declare(strict_types=1);
namespace App\Pipeline\Step;

use Waaseyaa\AI\Pipeline\PipelineContext;
use Waaseyaa\AI\Pipeline\PipelineStepInterface;
use Waaseyaa\AI\Pipeline\StepResult;

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
