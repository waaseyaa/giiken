<?php

declare(strict_types=1);

namespace App\Http\RateLimit;

final class FileRequestRateLimiter implements RequestRateLimiterInterface
{
    public function __construct(
        private readonly string $storageDirectory,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
    ) {}

    public function consume(string $bucketKey): RateLimitResult
    {
        if (!is_dir($this->storageDirectory) && !mkdir($concurrentDirectory = $this->storageDirectory, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException('Failed to create rate limit storage directory.');
        }

        $path = $this->storageDirectory . '/' . hash('sha256', $bucketKey) . '.json';
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open rate limit bucket.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Failed to lock rate limit bucket.');
            }

            $raw = stream_get_contents($handle);
            $timestamps = $this->decodeTimestamps($raw !== false ? $raw : '');
            $now = time();
            $windowStart = $now - $this->windowSeconds;
            $timestamps = array_values(array_filter(
                $timestamps,
                static fn (int $timestamp): bool => $timestamp >= $windowStart,
            ));

            if (count($timestamps) >= $this->maxAttempts) {
                $retryAfterSeconds = max(1, ($timestamps[0] + $this->windowSeconds) - $now);
                $this->writeTimestamps($handle, $timestamps);

                return new RateLimitResult(false, $this->maxAttempts, 0, $retryAfterSeconds);
            }

            $timestamps[] = $now;
            $this->writeTimestamps($handle, $timestamps);
            $remaining = max(0, $this->maxAttempts - count($timestamps));

            return new RateLimitResult(true, $this->maxAttempts, $remaining, 0);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return int[]
     */
    private function decodeTimestamps(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): ?int => is_int($value) ? $value : null, $decoded),
            static fn (?int $value): bool => $value !== null,
        ));
    }

    /**
     * @param resource $handle
     * @param int[] $timestamps
     */
    private function writeTimestamps($handle, array $timestamps): void
    {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($timestamps, JSON_THROW_ON_ERROR));
        fflush($handle);
    }
}
