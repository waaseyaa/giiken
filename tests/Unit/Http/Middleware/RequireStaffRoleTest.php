<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Middleware;

use App\Http\Middleware\RequireStaffRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(RequireStaffRole::class)]
final class RequireStaffRoleTest extends TestCase
{
    #[Test]
    public function allows_admin(): void
    {
        $account = $this->makeAccount(['giiken.community.comm-1.admin']);
        self::assertTrue((new RequireStaffRole())->check($account, 'comm-1'));
    }

    #[Test]
    public function allows_staff(): void
    {
        $account = $this->makeAccount(['giiken.community.comm-1.staff']);
        self::assertTrue((new RequireStaffRole())->check($account, 'comm-1'));
    }

    #[Test]
    public function allows_knowledge_keeper(): void
    {
        $account = $this->makeAccount(['giiken.community.comm-1.knowledge_keeper']);
        self::assertTrue((new RequireStaffRole())->check($account, 'comm-1'));
    }

    #[Test]
    public function denies_member(): void
    {
        $account = $this->makeAccount(['giiken.community.comm-1.member']);
        self::assertFalse((new RequireStaffRole())->check($account, 'comm-1'));
    }

    #[Test]
    public function denies_unauthenticated(): void
    {
        self::assertFalse((new RequireStaffRole())->check(null, 'comm-1'));
    }

    /**
     * @param array<int, string> $roles
     */
    private function makeAccount(array $roles): AccountInterface
    {
        return new class($roles) implements AccountInterface {
            /**
             * @param array<int, string> $roles
             */
            public function __construct(private readonly array $roles) {}
            public function id(): int|string { return '1'; }
            public function getRoles(): array { return $this->roles; }
            public function isAuthenticated(): bool { return true; }
            public function hasPermission(string $permission): bool { return false; }
        };
    }
}
