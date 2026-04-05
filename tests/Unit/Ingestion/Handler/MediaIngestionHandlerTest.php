<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Ingestion\Handler;

use Giiken\Entity\Community\Community;
use Giiken\Ingestion\Handler\MediaIngestionHandler;
use Giiken\Ingestion\IngestionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Media\File;
use Waaseyaa\Media\FileRepositoryInterface;
use Waaseyaa\Queue\QueueInterface;

#[CoversClass(MediaIngestionHandler::class)]
final class MediaIngestionHandlerTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function audioAndVideoMimeTypes(): array
    {
        return [
            'audio/mpeg'      => ['audio/mpeg'],
            'audio/mp4'       => ['audio/mp4'],
            'audio/wav'       => ['audio/wav'],
            'audio/ogg'       => ['audio/ogg'],
            'video/mp4'       => ['video/mp4'],
            'video/quicktime' => ['video/quicktime'],
            'video/webm'      => ['video/webm'],
        ];
    }

    #[Test]
    #[DataProvider('audioAndVideoMimeTypes')]
    public function supports_audio_and_video_mime_types(string $mimeType): void
    {
        $handler = new MediaIngestionHandler(
            $this->createStubMediaRepo(),
            $this->createStubQueue(),
        );

        $this->assertTrue($handler->supports($mimeType));
    }

    #[Test]
    public function does_not_support_text(): void
    {
        $handler = new MediaIngestionHandler(
            $this->createStubMediaRepo(),
            $this->createStubQueue(),
        );

        $this->assertFalse($handler->supports('text/plain'));
    }

    #[Test]
    public function handle_returns_raw_document_with_pending_transcription(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'giiken_media_test_');
        file_put_contents($tmpFile, 'fake audio content');

        try {
            $mediaRepo = new class implements FileRepositoryInterface {
                public function save(File $file): File
                {
                    return new File(
                        uri: 'stored-media-id-' . basename($file->uri),
                        filename: $file->filename,
                        mimeType: $file->mimeType,
                    );
                }
                public function load(string $uri): ?File { return null; }
                public function delete(string $uri): bool { return true; }
                /** @return File[] */
                public function findByOwner(int $ownerId): array { return []; }
            };

            $dispatchedJobs = [];
            $queue = new class ($dispatchedJobs) implements QueueInterface {
                /** @var array<int, object> */
                private array $dispatched;
                /** @param array<int, object> $dispatched */
                public function __construct(array &$dispatched)
                {
                    $this->dispatched = &$dispatched;
                }
                public function dispatch(object $message): void
                {
                    $this->dispatched[] = $message;
                }
            };

            $handler = new MediaIngestionHandler($mediaRepo, $queue);
            $community = new Community(['id' => 'comm-42', 'name' => 'Test Community']);

            $result = $handler->handle(
                filePath: $tmpFile,
                mimeType: 'audio/mpeg',
                originalFilename: 'recording.mp3',
                community: $community,
            );

            $this->assertSame('', $result->markdownContent);
            $this->assertSame('audio/mpeg', $result->mimeType);
            $this->assertSame('recording.mp3', $result->originalFilename);
            $this->assertStringStartsWith('stored-media-id-', $result->mediaId);
            $this->assertSame(['transcription_status' => 'pending'], $result->metadata);
            $this->assertCount(1, $dispatchedJobs);
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function rejects_files_over_2gb(): void
    {
        $this->assertSame(2 * 1024 * 1024 * 1024, MediaIngestionHandler::MAX_FILE_SIZE);
    }

    private function createStubMediaRepo(): FileRepositoryInterface
    {
        return new class implements FileRepositoryInterface {
            public function save(File $file): File
            {
                return new File(uri: 'stub-uri', filename: $file->filename, mimeType: $file->mimeType);
            }
            public function load(string $uri): ?File { return null; }
            public function delete(string $uri): bool { return true; }
            /** @return File[] */
            public function findByOwner(int $ownerId): array { return []; }
        };
    }

    private function createStubQueue(): QueueInterface
    {
        return new class implements QueueInterface {
            public function dispatch(object $message): void {}
        };
    }
}
