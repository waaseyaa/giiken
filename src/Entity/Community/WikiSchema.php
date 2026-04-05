<?php

declare(strict_types=1);

namespace Giiken\Entity\Community;

final class WikiSchema
{
    /**
     * @param list<string> $knowledgeTypes
     */
    public function __construct(
        public readonly string $defaultLanguage = 'en',
        public readonly array $knowledgeTypes = [],
        public readonly string $llmInstructions = '',
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            defaultLanguage: (string) ($data['default_language'] ?? 'en'),
            knowledgeTypes: isset($data['knowledge_types']) && is_array($data['knowledge_types'])
                ? array_values(array_map('strval', $data['knowledge_types']))
                : [],
            llmInstructions: (string) ($data['llm_instructions'] ?? ''),
        );
    }

    /**
     * @return array{default_language: string, knowledge_types: list<string>, llm_instructions: string}
     */
    public function toArray(): array
    {
        return [
            'default_language' => $this->defaultLanguage,
            'knowledge_types' => $this->knowledgeTypes,
            'llm_instructions' => $this->llmInstructions,
        ];
    }
}
