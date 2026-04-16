<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Provider\AppServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

#[CoversClass(AppServiceProvider::class)]
final class AppServiceProviderTest extends TestCase
{
    #[Test]
    public function it_extends_service_provider(): void
    {
        $this->assertTrue(
            is_subclass_of(AppServiceProvider::class, ServiceProvider::class),
        );
    }

    #[Test]
    public function register_is_callable(): void
    {
        $this->assertTrue(method_exists(AppServiceProvider::class, 'register'));
    }

    #[Test]
    public function routes_is_callable(): void
    {
        $this->assertTrue(method_exists(AppServiceProvider::class, 'routes'));
    }

    #[Test]
    public function boot_fails_loudly_when_northcloud_registry_is_unavailable(): void
    {
        $provider = new AppServiceProvider();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('NorthCloud MapperRegistry could not be resolved');

        $provider->boot();
    }
}
