<?php

declare(strict_types=1);

namespace App\Http\Api\Ask;

final class AskRequestValidator
{
    public function __construct(
        private readonly int $maxQuestionLength,
    ) {}

    /**
     * @param array<string, mixed> $body
     */
    public function validate(array $body): AskRequestData
    {
        $communitySlug = trim((string) ($body['communitySlug'] ?? ''));
        $question = trim((string) ($body['question'] ?? ''));

        if ($communitySlug === '' || $question === '') {
            throw new \InvalidArgumentException('communitySlug and question are required.');
        }

        if (mb_strlen($question) > $this->maxQuestionLength) {
            throw new \InvalidArgumentException(
                "question must be {$this->maxQuestionLength} characters or fewer.",
            );
        }

        return new AskRequestData(
            communitySlug: $communitySlug,
            question: $question,
        );
    }
}
