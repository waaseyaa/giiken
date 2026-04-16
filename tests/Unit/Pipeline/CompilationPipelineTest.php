<?php
declare(strict_types=1);
namespace App\Tests\Unit\Pipeline;

use App\Entity\KnowledgeItem\AccessTier;
use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use App\Entity\KnowledgeItem\KnowledgeType;
use App\Ingestion\RawDocument;
use App\Pipeline\CompilationPayload;
use App\Pipeline\CompilationPipeline;
use App\Pipeline\PipelineException;
use App\Pipeline\Provider\EmbeddingProviderInterface;
use App\Pipeline\Provider\LlmProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
        $repo = new class($savedItems) implements KnowledgeItemRepositoryInterface {
            /**
             * @param array<KnowledgeItem> $savedItems
             * @phpstan-ignore-next-line
             */
            public function __construct(private array &$savedItems) {}
            public function find(string $id): ?KnowledgeItem { return null; }
            public function findByCommunity(string $communityId): array { return []; }
            public function save(KnowledgeItem $item): void { $this->savedItems[] = $item; }
            public function delete(KnowledgeItem $item): void {}
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

        $result = $pipeline->compile($rawDoc, 'comm-1');

        $this->assertInstanceOf(CompilationPayload::class, $result);
        $this->assertNotNull($result->entityUuid);
        $this->assertSame('Council Minutes — Solar Vote', $result->title);
        $this->assertSame(KnowledgeType::Governance, $result->knowledgeType);
        $this->assertCount(1, $savedItems);
        $this->assertSame('Council Minutes — Solar Vote', $savedItems[0]->getTitle());
        $this->assertSame(KnowledgeType::Governance, $savedItems[0]->getKnowledgeType());
        $this->assertSame(AccessTier::Public, $savedItems[0]->getAccessTier());
    }

    #[Test]
    public function it_honors_access_tier_override(): void
    {
        $llm = $this->makeLlm('governance', $this->validStructureJson());
        $savedItems = [];
        $repo = $this->makeCollectingRepo($savedItems);
        $embeddings = $this->makeNullEmbeddings();

        $pipeline = new CompilationPipeline($llm, $embeddings, $repo);

        $result = $pipeline->compile(
            $this->makeRawDoc(),
            'comm-1',
            accessTier: AccessTier::Members,
        );

        $this->assertSame(AccessTier::Members, $result->accessTier);
        $this->assertCount(1, $savedItems);
        $this->assertSame(AccessTier::Members, $savedItems[0]->getAccessTier());
    }

    #[Test]
    public function it_skips_classify_llm_when_type_is_forced(): void
    {
        $llmCallCount = 0;
        $llm = new class($llmCallCount, $this->validStructureJson()) implements LlmProviderInterface {
            public function __construct(
                private int &$callCount,
                private readonly string $structureResponse,
            ) {}
            public function complete(string $systemPrompt, string $userPrompt): string
            {
                $this->callCount++;
                // When `--type` is forced, ClassifyStep must not call us at
                // all; so the only LLM call permitted is StructureStep.
                return $this->structureResponse;
            }
        };
        $savedItems = [];
        $repo = $this->makeCollectingRepo($savedItems);
        $embeddings = $this->makeNullEmbeddings();

        $pipeline = new CompilationPipeline($llm, $embeddings, $repo);
        $result = $pipeline->compile(
            $this->makeRawDoc(),
            'comm-1',
            forcedType: KnowledgeType::Land,
        );

        $this->assertSame(1, $llmCallCount, 'ClassifyStep must not call the LLM when type is forced');
        $this->assertSame(KnowledgeType::Land, $result->knowledgeType);
        $this->assertSame(KnowledgeType::Land, $savedItems[0]->getKnowledgeType());
    }

    #[Test]
    public function it_does_not_persist_in_dry_run(): void
    {
        $llm = $this->makeLlm('governance', $this->validStructureJson());
        $savedItems = [];
        $repo = $this->makeCollectingRepo($savedItems);
        $storedEmbeddings = [];
        $embeddings = new class($storedEmbeddings) implements EmbeddingProviderInterface {
            /** @phpstan-ignore-next-line */
            public function __construct(private array &$storedEmbeddings) {}
            public function embed(string $text): array { return [0.1]; }
            public function search(string $query, string $communityId, int $limit = 5): array { return []; }
            public function store(string $entityId, string $text, string $communityId): void
            {
                $this->storedEmbeddings[] = $entityId;
            }
        };

        $pipeline = new CompilationPipeline($llm, $embeddings, $repo);
        $result = $pipeline->compile(
            $this->makeRawDoc(),
            'comm-1',
            dryRun: true,
        );

        $this->assertTrue($result->dryRun);
        $this->assertNotNull($result->entityUuid, 'Dry run still assigns a uuid for display');
        $this->assertSame([], $savedItems, 'Dry run must not call repository->save()');
        $this->assertSame([], $storedEmbeddings, 'Dry run must not call embeddings->store()');
    }

    private function makeLlm(string $classifyResponse, string $structureResponse): LlmProviderInterface
    {
        $callIndex = 0;

        return new class($classifyResponse, $structureResponse, $callIndex) implements LlmProviderInterface {
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
    }

    /**
     * @param array<KnowledgeItem> $savedItems
     */
    private function makeCollectingRepo(array &$savedItems): KnowledgeItemRepositoryInterface
    {
        return new class($savedItems) implements KnowledgeItemRepositoryInterface {
            /** @phpstan-ignore-next-line */
            public function __construct(private array &$savedItems) {}
            public function find(string $id): ?KnowledgeItem { return null; }
            public function findByCommunity(string $communityId): array { return []; }
            public function save(KnowledgeItem $item): void { $this->savedItems[] = $item; }
            public function delete(KnowledgeItem $item): void {}
        };
    }

    private function makeNullEmbeddings(): EmbeddingProviderInterface
    {
        return new class implements EmbeddingProviderInterface {
            public function embed(string $text): array { return [0.1]; }
            public function search(string $query, string $communityId, int $limit = 5): array { return []; }
            public function store(string $entityId, string $text, string $communityId): void {}
        };
    }

    private function validStructureJson(): string
    {
        return json_encode([
            'title' => 'Council Minutes — Solar Vote',
            'summary' => 'Council voted on the solar project.',
            'people' => ['Mayor Smith'],
            'places' => ['Massey'],
            'topics' => ['solar'],
            'key_passages' => ['Motion passed 4-1.'],
        ], JSON_THROW_ON_ERROR);
    }

    private function makeRawDoc(): RawDocument
    {
        return new RawDocument(
            markdownContent: "# Council Meeting\n\nThe council voted on the solar project.",
            mimeType: 'application/pdf',
            originalFilename: 'minutes.pdf',
            mediaId: 'media-123',
        );
    }

    #[Test]
    public function it_wraps_step_failure_in_pipeline_exception(): void
    {
        $llm = new class implements LlmProviderInterface {
            public function complete(string $s, string $u): string { return 'invalid_type'; }
        };
        $repo = new class implements KnowledgeItemRepositoryInterface {
            public function find(string $id): ?KnowledgeItem { return null; }
            public function findByCommunity(string $communityId): array { return []; }
            public function save(KnowledgeItem $item): void {}
            public function delete(KnowledgeItem $item): void {}
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
