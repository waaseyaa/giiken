<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Ingestion\Job;

use Giiken\Ingestion\Job\TranscribeJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranscribeJob::class)]
final class TranscribeJobTest extends TestCase
{
    #[Test]
    public function constructs_with_required_params(): void
    {
        $job = new TranscribeJob(
            mediaId: 'media-abc',
            communityId: 'comm-123',
            originalFilename: 'interview.mp4',
        );

        $this->assertSame('media-abc', $job->mediaId);
        $this->assertSame('comm-123', $job->communityId);
        $this->assertSame('interview.mp4', $job->originalFilename);
    }

    #[Test]
    public function timeout_is_five_minutes(): void
    {
        $job = new TranscribeJob(
            mediaId: 'media-abc',
            communityId: 'comm-123',
            originalFilename: 'interview.mp4',
        );

        $this->assertSame(300, $job->timeout);
    }

    #[Test]
    public function does_not_retry(): void
    {
        $job = new TranscribeJob(
            mediaId: 'media-abc',
            communityId: 'comm-123',
            originalFilename: 'interview.mp4',
        );

        $this->assertSame(1, $job->tries);
    }
}
