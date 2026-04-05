<?php
declare(strict_types=1);
namespace Giiken\Tests\Unit\Pipeline;

use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Ingestion\RawDocument;
use Giiken\Pipeline\CompilationPipeline;
use Giiken\Pipeline\PipelineException;
use Giiken\Pipeline\Provider\EmbeddingProviderInterface;
use Giiken\Pipeline\Provider\LlmProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

#[CoversClass(CompilationPipeline::class)]
final class CompilationPipelineTest extends TestCase
{
    #[Test]
    public function it_runs_full_pipeline_and_produces_knowledge_item(): void
    {
        $classifyResponse = 'governance';
        $structureResponse = json_encode([
            'title' => 'Council Minutes — Solar Vote',
            'summary' => 'Council voted on the solar project.',
            'people' => ['Mayor Smith'],
            'places' => ['Massey'],
            'topics' => ['solar'],
            'key_passages' => ['Motion passed 4-1.'],
        ], JSON_THROW_ON_ERROR);

        $callIndex = 0;
        $llm = new class($classifyResponse, $structureResponse, $callIndex) implements LlmProviderInterface {
            public function __construct(
                private readonly string $classifyResponse,
                private readonly string $structureResponse,
                private int &$callIndex,
            ) {}
            public function complete(string $systemPrompt, string $userPrompt): string
            {
                $this->callIndex++;
                return $this->callIndex === 1 ? $this->classifyResponse : $this->structureResponse;
            }
        };

        $savedItems = [];
        $repo = new class($savedItems) implements EntityRepositoryInterface {
            /**
             * @param array<EntityInterface> $savedItems
             * @phpstan-ignore-next-line
             */
            public function __construct(private array &$savedItems) {}
            public function find(string $id, ?string $langcode = null, bool $fallback = false): ?EntityInterface { return null; }
            public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null): array { return []; }
            public function save(EntityInterface $entity, bool $validate = true): int { $this->savedItems[] = $entity; return 1; }
            public function delete(EntityInterface $entity): void {}
            public function exists(string $id): bool { return false; }
            public function count(array $criteria = []): int { return 0; }
            public function loadRevision(string $entityId, int $revisionId): ?EntityInterface { return null; }
            public function rollback(string $entityId, int $targetRevisionId): EntityInterface { throw new \RuntimeException('Not implemented'); }
            public function saveMany(array $entities, bool $validate = true): array { return []; }
            public function deleteMany(array $entities): int { return 0; }
        };

        $embeddings = new class implements EmbeddingProviderInterface {
            public function embed(string $text): array { return [0.1]; }
            public function search(string $query, string $communityId, int $limit = 5): array { return []; }
            public function store(string $entityId, string $text, string $communityId): void {}
        };

        $pipeline = new CompilationPipeline($llm, $embeddings, $repo);
        $rawDoc = new RawDocument(
            markdownContent: "# Council Meeting\n\nThe council voted on the solar project.",
            mimeType: 'application/pdf',
            originalFilename: 'minutes.pdf',
            mediaId: 'media-123',
        );

        $pipeline->compile($rawDoc, 'comm-1');

        $this->assertCount(1, $savedItems);
        $this->assertSame('Council Minutes — Solar Vote', $savedItems[0]->getTitle());
        $this->assertSame(KnowledgeType::Governance, $savedItems[0]->getKnowledgeType());
    }

    #[Test]
    public function it_wraps_step_failure_in_pipeline_exception(): void
    {
        $llm = new class implements LlmProviderInterface {
            public function complete(string $s, string $u): string { return 'invalid_type'; }
        };
        $repo = new class implements EntityRepositoryInterface {
            public function find(string $id, ?string $langcode = null, bool $fallback = false): ?EntityInterface { return null; }
            public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null): array { return []; }
            public function save(EntityInterface $entity, bool $validate = true): int { return 1; }
            public function delete(EntityInterface $entity): void {}
            public function exists(string $id): bool { return false; }
            public function count(array $criteria = []): int { return 0; }
            public function loadRevision(string $entityId, int $revisionId): ?EntityInterface { return null; }
            public function rollback(string $entityId, int $targetRevisionId): EntityInterface { throw new \RuntimeException('Not implemented'); }
            public function saveMany(array $entities, bool $validate = true): array { return []; }
            public function deleteMany(array $entities): int { return 0; }
        };
        $embeddings = new class implements EmbeddingProviderInterface {
            public function embed(string $text): array { return []; }
            public function search(string $query, string $communityId, int $limit = 5): array { return []; }
            public function store(string $entityId, string $text, string $communityId): void {}
        };

        $pipeline = new CompilationPipeline($llm, $embeddings, $repo);
        $rawDoc = new RawDocument(
            markdownContent: 'Some content',
            mimeType: 'application/pdf',
            originalFilename: 'test.pdf',
            mediaId: 'media-1',
        );

        $this->expectException(PipelineException::class);
        $pipeline->compile($rawDoc, 'comm-1');
    }
}
