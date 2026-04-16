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
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the failure paths of {@see IngestFileCommand}. The happy
 * path (markdown → persisted KnowledgeItem) runs the real pipeline end-
 * to-end and is exercised manually; giiken#95 will add a seam that lets
 * us automate it without hitting a real LLM.
 */
#[CoversClass(IngestFileCommand::class)]
final class IngestFileCommandTest extends TestCase
{
    #[Test]
    public function it_fails_when_file_does_not_exist(): void
    {
        $tester = $this->makeTester($this->buildCommand());

        $exit = $tester->execute([
            'community-slug' => 'test-community',
            'file'           => '/tmp/this-file-does-not-exist-' . bin2hex(random_bytes(8)),
        ]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('File not found', $tester->getDisplay());
    }

    #[Test]
    public function it_fails_when_community_slug_is_unknown(): void
    {
        $file = $this->writeTempFile('.md', '# Sample');

        try {
            $tester = $this->makeTester($this->buildCommand(community: null));

            $exit = $tester->execute([
                'community-slug' => 'no-such-community',
                'file'           => $file,
            ]);

            self::assertSame(Command::FAILURE, $exit);
            self::assertStringContainsString('Community not found', $tester->getDisplay());
        } finally {
            @unlink($file);
        }
    }

    #[Test]
    public function it_fails_with_helpful_message_when_no_handler_supports_the_file(): void
    {
        // `.xyzbogusext` maps to nothing in the extension map and no
        // handler is registered, so `IngestionHandlerRegistry::handle`
        // will throw IngestionException which we expect the command to
        // surface cleanly.
        $file = $this->writeTempFile('.xyzbogusext', 'binary-ish content');

        try {
            $tester = $this->makeTester($this->buildCommand());

            $exit = $tester->execute([
                'community-slug' => 'test-community',
                'file'           => $file,
            ]);

            self::assertSame(Command::FAILURE, $exit);
            self::assertStringContainsString('Ingestion handler failed', $tester->getDisplay());
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

        // Empty registry — the command should fail at `handle()` before
        // ever reaching the pipeline, so the pipeline's providers are
        // never exercised.
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

    private function makeTester(IngestFileCommand $command): CommandTester
    {
        $app = new Application();
        $app->addCommands([$command]);

        return new CommandTester($app->find('giiken:ingest:file'));
    }
}
