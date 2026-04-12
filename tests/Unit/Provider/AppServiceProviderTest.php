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
}
