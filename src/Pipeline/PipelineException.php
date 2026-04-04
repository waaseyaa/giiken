<?php

declare(strict_types=1);

namespace Giiken\Pipeline;

final class PipelineException extends \RuntimeException
{
    public static function fromStep(string $stepName, \Throwable $previous): self
    {
        return new self(
            "Pipeline failed at step '{$stepName}': {$previous->getMessage()}",
            0,
            $previous,
        );
    }
}
