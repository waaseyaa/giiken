<?php
declare(strict_types=1);
namespace App\Tests\Unit\Pipeline\Step;

use App\Pipeline\CompilationPayload;
use App\Pipeline\Provider\EmbeddingProviderInterface;
use App\Pipeline\Step\LinkStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Pipeline\PipelineContext;

#[CoversClass(LinkStep::class)]
final class LinkStepTest extends TestCase
{
    #[Test]
    public function it_links_to_similar_items_above_threshold(): void
    {
        $embeddings = $this->createEmbeddingProvider([
            ['id' => 'item-1', 'score' => 0.92],
            ['id' => 'item-2', 'score' => 0.85],
            ['id' => 'item-3', 'score' => 0.78],
            ['id' => 'item-4', 'score' => 0.60],
        ]);

        $step = new LinkStep($embeddings);
        $payload = new CompilationPayload();
        $payload->title = 'Solar Project Update';
        $payload->summary = 'Council reviewed the proposal.';
        $payload->topics = ['solar', 'council'];
        $payload->communityId = 'comm-1';

        $context = new PipelineContext(pipelineId: 'test', startedAt: time());
        $result = $step->process(['payload' => $payload], $context);

        $this->assertTrue($result->success);
        $this->assertSame(['item-1', 'item-2', 'item-3'], $payload->linkedItemIds);
    }

    #[Test]
    public function it_excludes_results_below_threshold(): void
    {
        $embeddings = $this->createEmbeddingProvider([
            ['id' => 'item-1', 'score' => 0.70],
            ['id' => 'item-2', 'score' => 0.50],
        ]);

        $step = new LinkStep($embeddings);
        $payload = new CompilationPayload();
        $payload->title = 'Test';
        $payload->summary = 'Test';
        $payload->topics = [];
        $payload->communityId = 'comm-1';

        $context = new PipelineContext(pipelineId: 'test', startedAt: time());
        $step->process(['payload' => $payload], $context);
        $this->assertSame([], $payload->linkedItemIds);
    }

    #[Test]
    public function it_limits_to_top_five(): void
    {
        $results = [];
        for ($i = 1; $i <= 8; $i++) {
            $results[] = ['id' => "item-{$i}", 'score' => 1.0 - ($i * 0.01)];
        }

        $embeddings = $this->createEmbeddingProvider($results);
        $step = new LinkStep($embeddings);
        $payload = new CompilationPayload();
        $payload->title = 'Test';
        $payload->summary = 'Test';
        $payload->topics = [];
        $payload->communityId = 'comm-1';

        $context = new PipelineContext(pipelineId: 'test', startedAt: time());
        $step->process(['payload' => $payload], $context);
        $this->assertCount(5, $payload->linkedItemIds);
    }

    /** @param array<array{id: string, score: float}> $results */
    private function createEmbeddingProvider(array $results): EmbeddingProviderInterface
    {
        return new class($results) implements EmbeddingProviderInterface {
            /** @param array<array{id: string, score: float}> $results */
            public function __construct(private readonly array $results) {}
            public function embed(string $text): array { return [0.1, 0.2]; }
            public function search(string $query, string $communityId, int $limit = 5): array { return $this->results; }
            public function store(string $entityId, string $text, string $communityId): void {}
        };
    }
}
