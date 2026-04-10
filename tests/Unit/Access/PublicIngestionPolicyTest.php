<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Access;

use Giiken\Access\PublicIngestionPolicy;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(PublicIngestionPolicy::class)]
final class PublicIngestionPolicyTest extends TestCase
{
    private const COMMUNITY_A = 'community-a';

    private PublicIngestionPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new PublicIngestionPolicy();
    }

    #[Test]
    public function it_applies_to_knowledge_items(): void
    {
        $this->assertTrue($this->policy->appliesTo('knowledge_item'));
        $this->assertFalse($this->policy->appliesTo('community'));
    }

    #[Test]
    public function anonymous_can_view_public_items(): void
    {
        $result = $this->policy->access(
            $this->item(),
            'view',
            $this->account(id: '0', roles: []),
        );

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anyone_can_create_knowledge_items(): void
    {
        $result = $this->policy->createAccess(
            'knowledge_item',
            'default',
            $this->account(id: '0', roles: []),
        );

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_update(): void
    {
        $result = $this->policy->access(
            $this->item(),
            'update',
            $this->account(id: '0', roles: []),
        );

        $this->assertTrue($result->isForbidden());
    }

    #[Test]
    public function authenticated_user_can_update(): void
    {
        $result = $this->policy->access(
            $this->item(),
            'update',
            $this->account(id: '1', roles: []),
        );

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_delete(): void
    {
        $result = $this->policy->access(
            $this->item(),
            'delete',
            $this->account(id: '0', roles: []),
        );

        $this->assertTrue($result->isForbidden());
    }

    #[Test]
    public function non_admin_member_cannot_delete(): void
    {
        $result = $this->policy->access(
            $this->item(),
            'delete',
            $this->account(id: '1', roles: ["giiken.community." . self::COMMUNITY_A . ".member"]),
        );

        $this->assertTrue($result->isForbidden());
    }

    #[Test]
    public function admin_can_delete(): void
    {
        $result = $this->policy->access(
            $this->item(),
            'delete',
            $this->account(id: '99', roles: ["giiken.community." . self::COMMUNITY_A . ".admin"]),
        );

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function admin_from_different_community_cannot_delete(): void
    {
        $result = $this->policy->access(
            $this->item(),
            'delete',
            $this->account(id: '99', roles: ["giiken.community.community-b.admin"]),
        );

        $this->assertTrue($result->isForbidden());
    }

    private function item(): KnowledgeItem
    {
        return KnowledgeItem::make([
            'community_id' => self::COMMUNITY_A,
            'title'        => 'Test Item',
            'content'      => 'Body',
            'access_tier'  => 'public',
        ]);
    }

    /** @param string[] $roles */
    private function account(string $id, array $roles): AccountInterface
    {
        return new class($id, $roles) implements AccountInterface {
            /** @param string[] $roles */
            public function __construct(
                private readonly string $id,
                private readonly array $roles,
            ) {}

            public function id(): int|string { return $this->id; }
            public function getRoles(): array { return $this->roles; }
            public function isAuthenticated(): bool { return $this->id !== '0'; }
            public function hasPermission(string $permission): bool { return false; }
        };
    }
}
