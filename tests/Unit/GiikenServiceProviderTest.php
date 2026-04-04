<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit;

use Giiken\GiikenServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

#[CoversClass(GiikenServiceProvider::class)]
final class GiikenServiceProviderTest extends TestCase
{
    #[Test]
    public function it_extends_service_provider(): void
    {
        $this->assertTrue(
            is_subclass_of(GiikenServiceProvider::class, ServiceProvider::class),
        );
    }

    #[Test]
    public function register_is_callable(): void
    {
        $this->assertTrue(method_exists(GiikenServiceProvider::class, 'register'));
    }

    #[Test]
    public function routes_is_callable(): void
    {
        $this->assertTrue(method_exists(GiikenServiceProvider::class, 'routes'));
    }
}
