<?php
declare(strict_types=1);
namespace Giiken\Tests\Unit\Pipeline\Step;

use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Pipeline\CompilationPayload;
use Giiken\Pipeline\Provider\EmbeddingProviderInterface;
use Giiken\Pipeline\Step\EmbedStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AiPipeline\PipelineContext;
use Waaseyaa\Entity\EntityRepositoryInterface;

#[CoversClass(EmbedStep::class)]
final class EmbedStepTest extends TestCase
{
    #[Test]
    public function it_creates_knowledge_item_and_stores_embedding(): void
    {
        $storedEntityId = null;
        $storedText = null;
        $storedCommunityId = null;

        $embeddings = new class($storedEntityId, $storedText, $storedCommunityId) implements EmbeddingProviderInterface {
            public function __construct(private ?string &$storedEntityId, private ?string &$storedText, private ?string &$storedCommunityId) {}
            public function embed(string $text): array { return [0.1]; }
            public function search(string $query, string $communityId, int $limit = 5): array { return []; }
            public function store(string $entityId, string $text, string $communityId): void
            {
                $this->storedEntityId = $entityId;
                $this->storedText = $text;
                $this->storedCommunityId = $communityId;
            }
        };

        $savedItem = null;
        $repo = new class($savedItem) implements EntityRepositoryInterface {
            public function __construct(private ?KnowledgeItem &$savedItem) {}
            public function save(object $entity): void { $this->savedItem = $entity; }
            public function load(string $id): ?object { return null; }
            public function delete(string $id): void {}
        };

        $step = new EmbedStep($embeddings, $repo);
        $payload = new CompilationPayload();
        $payload->communityId = 'comm-1';
        $payload->title = 'Solar Update';
        $payload->content = "# Solar Update\n\nDetails here.";
        $payload->summary = 'A summary.';
        $payload->mediaId = 'media-1';

        $context = new PipelineContext(['payload' => $payload]);
        $result = $step->process(['payload' => $payload], $context);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($savedItem);
        $this->assertSame('Solar Update', $savedItem->getTitle());
        $this->assertSame('comm-1', $storedCommunityId);
        $this->assertStringContainsString('Solar Update', $storedText);

        // Entity ID used for embedding must match the UUID on the saved item
        $this->assertNotNull($storedEntityId);
        $this->assertSame((string) $savedItem->get('uuid'), $storedEntityId);
    }

    #[Test]
    public function it_describes_itself(): void
    {
        $embeddings = new class implements EmbeddingProviderInterface {
            public function embed(string $text): array { return []; }
            public function search(string $query, string $communityId, int $limit = 5): array { return []; }
            public function store(string $entityId, string $text, string $communityId): void {}
        };
        $repo = new class implements EntityRepositoryInterface {
            public function save(object $entity): void {}
            public function load(string $id): ?object { return null; }
            public function delete(string $id): void {}
        };

        $step = new EmbedStep($embeddings, $repo);
        $this->assertSame('Generate vector embedding and persist KnowledgeItem', $step->describe());
    }
}
