<?php

declare(strict_types=1);

namespace App\Tests\Unit\Query\Report;

use App\Query\Report\DateRange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateRange::class)]
final class DateRangeTest extends TestCase
{
    #[Test]
    public function contains_date_within_range(): void
    {
        $range = new DateRange(
            from: new \DateTimeImmutable('2025-01-01'),
            to: new \DateTimeImmutable('2025-12-31'),
        );

        $this->assertTrue($range->contains(new \DateTimeImmutable('2025-06-15')));
    }

    #[Test]
    public function contains_date_on_from_boundary(): void
    {
        $range = new DateRange(
            from: new \DateTimeImmutable('2025-01-01'),
            to: new \DateTimeImmutable('2025-12-31'),
        );

        $this->assertTrue($range->contains(new \DateTimeImmutable('2025-01-01')));
    }

    #[Test]
    public function contains_date_on_to_boundary(): void
    {
        $range = new DateRange(
            from: new \DateTimeImmutable('2025-01-01'),
            to: new \DateTimeImmutable('2025-12-31'),
        );

        $this->assertTrue($range->contains(new \DateTimeImmutable('2025-12-31')));
    }

    #[Test]
    public function does_not_contain_date_before_range(): void
    {
        $range = new DateRange(
            from: new \DateTimeImmutable('2025-01-01'),
            to: new \DateTimeImmutable('2025-12-31'),
        );

        $this->assertFalse($range->contains(new \DateTimeImmutable('2024-12-31')));
    }

    #[Test]
    public function does_not_contain_date_after_range(): void
    {
        $range = new DateRange(
            from: new \DateTimeImmutable('2025-01-01'),
            to: new \DateTimeImmutable('2025-12-31'),
        );

        $this->assertFalse($range->contains(new \DateTimeImmutable('2026-01-01')));
    }

    #[Test]
    public function exposes_from_and_to_properties(): void
    {
        $from = new \DateTimeImmutable('2025-01-01');
        $to   = new \DateTimeImmutable('2025-12-31');

        $range = new DateRange(from: $from, to: $to);

        $this->assertSame($from, $range->from);
        $this->assertSame($to, $range->to);
    }
}
