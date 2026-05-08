<?php

declare(strict_types=1);

namespace App\Tests\Unit\Console;

use App\Console\IngestFileCommand;
use App\Entity\Community\Community;
use App\Entity\Community\CommunityRepositoryInterface;
use App\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use App\Ingestion\IngestionHandlerRegistry;
use App\Pipeline\CompilationPipeline;
use App\Pipeline\Provider\EmbeddingProviderInterface;
use App\Pipeline\Provider\LlmProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the failure paths of {@see IngestFileCommand::run()}.
 *
 * Symfony Console is only used by Waaseyaa to register the thin adapter from
 * {@see \App\Provider\AppServiceProvider::commands()}; orchestration is tested
 * directly via a capturing {@see \Closure(string):void}.
 */
#[CoversClass(IngestFileCommand::class)]
final class IngestFileCommandTest extends TestCase
{
    #[Test]
    public function it_fails_when_file_does_not_exist(): void
    {
        $capture = new IngestFileCommandOutputCapture();
        $exit = $this->buildCommand()->run(
            'test-community',
            '/tmp/this-file-does-not-exist-' . bin2hex(random_bytes(8)),
            $capture->writelnFn(),
        );

        self::assertSame(IngestFileCommand::EXIT_FAILURE, $exit);
        self::assertStringContainsString('File not found', $capture->buffer);
    }

    #[Test]
    public function it_fails_when_community_slug_is_unknown(): void
    {
        $file = $this->writeTempFile('.md', '# Sample');

        try {
            $capture = new IngestFileCommandOutputCapture();
            $exit = $this->buildCommand(community: null)->run(
                'no-such-community',
                $file,
                $capture->writelnFn(),
            );

            self::assertSame(IngestFileCommand::EXIT_FAILURE, $exit);
            self::assertStringContainsString('Community not found', $capture->buffer);
        } finally {
            @unlink($file);
        }
    }

    #[Test]
    public function it_fails_with_helpful_message_when_no_handler_supports_the_file(): void
    {
        $file = $this->writeTempFile('.xyzbogusext', 'binary-ish content');

        try {
            $capture = new IngestFileCommandOutputCapture();
            $exit = $this->buildCommand()->run(
                'test-community',
                $file,
                $capture->writelnFn(),
            );

            self::assertSame(IngestFileCommand::EXIT_FAILURE, $exit);
            self::assertStringContainsString('Ingestion handler failed', $capture->buffer);
        } finally {
            @unlink($file);
        }
    }

    private function buildCommand(?Community $community = null): IngestFileCommand
    {
        $community ??= $this->makeCommunity('test-community', '1');

        $communityRepo = $this->createMock(CommunityRepositoryInterface::class);
        $communityRepo->method('findBySlug')->willReturnCallback(
            static fn (string $slug): ?Community => $community !== null && $community->get('slug') === $slug
                ? $community
                : null,
        );

        $registry = new IngestionHandlerRegistry();

        $pipeline = new CompilationPipeline(
            $this->createMock(LlmProviderInterface::class),
            $this->createMock(EmbeddingProviderInterface::class),
            $this->createMock(KnowledgeItemRepositoryInterface::class),
        );

        return new IngestFileCommand($communityRepo, $registry, $pipeline);
    }

    private function makeCommunity(string $slug, string $id): Community
    {
        return Community::make([
            'id'     => $id,
            'uuid'   => '00000000-0000-0000-0000-000000000000',
            'name'   => 'Test',
            'bundle' => 'community',
            'slug'   => $slug,
        ]);
    }

    private function writeTempFile(string $suffix, string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ingest-test-');
        self::assertIsString($path);
        $final = $path . $suffix;
        rename($path, $final);
        file_put_contents($final, $contents);

        return $final;
    }
}

final class IngestFileCommandOutputCapture
{
    public string $buffer = '';

    /**
     * @return \Closure(string): void
     */
    public function writelnFn(): \Closure
    {
        return function (string $line): void {
            $this->buffer .= $line . "\n";
        };
    }
}
