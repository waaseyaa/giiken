<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Access;

use Giiken\Access\KnowledgeItemAccessPolicy;
use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(KnowledgeItemAccessPolicy::class)]
final class KnowledgeItemAccessPolicyTest extends TestCase
{
    private const COMMUNITY_A = 'community-a';
    private const COMMUNITY_B = 'community-b';
    private const USER_UUID   = 'user-uuid-123';

    private KnowledgeItemAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new KnowledgeItemAccessPolicy();
    }

    // ------------------------------------------------------------------
    // Public tier
    // ------------------------------------------------------------------

    #[Test]
    public function public_tier_allows_anonymous(): void
    {
        $result = $this->policy->access(
            $this->item(AccessTier::Public),
            'view',
            $this->account(id: '0', roles: []),
        );

        $this->assertTrue($result->isAllowed());
    }

    // ------------------------------------------------------------------
    // Members tier
    // ------------------------------------------------------------------

    #[Test]
    public function members_tier_denies_anonymous(): void
    {
        $result = $this->policy->access(
            $this->item(AccessTier::Members),
            'view',
            $this->account(id: '0', roles: []),
        );

        $this->assertTrue($result->isForbidden());
    }

    #[Test]
    public function members_tier_allows_member(): void
    {
        $result = $this->policy->access(
            $this->item(AccessTier::Members),
            'view',
            $this->account(id: '1', roles: ["giiken.community." . self::COMMUNITY_A . ".member"]),
        );

        $this->assertTrue($result->isAllowed());
    }

    // ------------------------------------------------------------------
    // Staff tier
    // ------------------------------------------------------------------

    #[Test]
    public function staff_tier_denies_member(): void
    {
        $result = $this->policy->access(
            $this->item(AccessTier::Staff),
            'view',
            $this->account(id: '1', roles: ["giiken.community." . self::COMMUNITY_A . ".member"]),
        );

        $this->assertTrue($result->isForbidden());
    }

    #[Test]
    public function staff_tier_allows_staff(): void
    {
        $result = $this->policy->access(
            $this->item(AccessTier::Staff),
            'view',
            $this->account(id: '2', roles: ["giiken.community." . self::COMMUNITY_A . ".staff"]),
        );

        $this->assertTrue($result->isAllowed());
    }

    // ------------------------------------------------------------------
    // Restricted tier — role-based
    // ------------------------------------------------------------------

    #[Test]
    public function restricted_tier_denies_staff_not_in_allowed_roles(): void
    {
        $result = $this->policy->access(
            $this->item(AccessTier::Restricted, allowedRoles: ['knowledge_keeper']),
            'view',
            $this->account(id: '2', roles: ["giiken.community." . self::COMMUNITY_A . ".staff"]),
        );

        $this->assertTrue($result->isForbidden());
    }

    #[Test]
    public function restricted_tier_allows_knowledge_keeper_by_role_rank(): void
    {
        $result = $this->policy->access(
            $this->item(AccessTier::Restricted, allowedRoles: ['knowledge_keeper']),
            'view',
            $this->account(id: '3', roles: ["giiken.community." . self::COMMUNITY_A . ".knowledge_keeper"]),
        );

        $this->assertTrue($result->isAllowed());
    }

    // ------------------------------------------------------------------
    // Restricted tier — user-based
    // ------------------------------------------------------------------

    #[Test]
    public function restricted_tier_denies_different_user(): void
    {
        $result = $this->policy->access(
            $this->item(AccessTier::Restricted, allowedUsers: [self::USER_UUID]),
            'view',
            $this->account(id: 'other-user', roles: ["giiken.community." . self::COMMUNITY_A . ".member"]),
        );

        $this->assertTrue($result->isForbidden());
    }

    #[Test]
    public function restricted_tier_allows_exact_user(): void
    {
        $result = $this->policy->access(
            $this->item(AccessTier::Restricted, allowedUsers: [self::USER_UUID]),
            'view',
            $this->account(id: self::USER_UUID, roles: ["giiken.community." . self::COMMUNITY_A . ".member"]),
        );

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function restricted_tier_allows_when_matching_either_roles_or_users(): void
    {
        $result = $this->policy->access(
            $this->item(
                AccessTier::Restricted,
                allowedRoles: ['knowledge_keeper'],
                allowedUsers: [self::USER_UUID],
            ),
            'view',
            $this->account(id: self::USER_UUID, roles: ["giiken.community." . self::COMMUNITY_A . ".member"]),
        );

        $this->assertTrue($result->isAllowed());
    }

    // ------------------------------------------------------------------
    // Admin always allowed
    // ------------------------------------------------------------------

    #[Test]
    public function admin_can_access_any_tier(): void
    {
        $admin = $this->account(id: '99', roles: ["giiken.community." . self::COMMUNITY_A . ".admin"]);

        foreach ([AccessTier::Public, AccessTier::Members, AccessTier::Staff, AccessTier::Restricted] as $tier) {
            $result = $this->policy->access($this->item($tier), 'view', $admin);
            $this->assertTrue($result->isAllowed(), "Admin failed on tier: {$tier->value}");
        }
    }

    // ------------------------------------------------------------------
    // Multi-tenancy: community A role does not bleed into community B
    // ------------------------------------------------------------------

    #[Test]
    public function community_a_knowledge_keeper_cannot_access_community_b_restricted(): void
    {
        $item = KnowledgeItem::make([
            'community_id'  => self::COMMUNITY_B,
            'title'         => 'Community B secret',
            'content'       => 'Body',
            'access_tier'   => AccessTier::Restricted->value,
            'allowed_roles' => json_encode(['knowledge_keeper'], JSON_THROW_ON_ERROR),
            'allowed_users' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        $account = $this->account(
            id: '5',
            roles: ["giiken.community." . self::COMMUNITY_A . ".knowledge_keeper"],
        );

        $result = $this->policy->access($item, 'view', $account);

        $this->assertTrue($result->isForbidden());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param string[] $allowedRoles
     * @param string[] $allowedUsers
     */
    private function item(
        AccessTier $tier,
        array $allowedRoles = [],
        array $allowedUsers = [],
    ): KnowledgeItem {
        return KnowledgeItem::make([
            'community_id'  => self::COMMUNITY_A,
            'title'         => 'Test Item',
            'content'       => 'Body',
            'access_tier'   => $tier->value,
            'allowed_roles' => json_encode($allowedRoles, JSON_THROW_ON_ERROR),
            'allowed_users' => json_encode($allowedUsers, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param string[] $roles
     */
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
