<?php

declare(strict_types=1);

namespace App\Http\RateLimit;

interface RequestRateLimiterInterface
{
    public function consume(string $bucketKey): RateLimitResult;
}
