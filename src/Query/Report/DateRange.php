<?php

declare(strict_types=1);

namespace Giiken\Query\Report;

final readonly class DateRange
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
    ) {}

    public function contains(\DateTimeImmutable $date): bool
    {
        return $date >= $this->from && $date <= $this->to;
    }
}
