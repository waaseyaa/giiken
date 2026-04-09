<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Query;

use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Query\SynthesisAccessCapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SynthesisAccessCapper::class)]
final class SynthesisAccessCapperTest extends TestCase
{
    #[Test]
    public function empty_citations_default_to_members_tier(): void
    {
        $cap = SynthesisAccessCapper::cap([]);
        self::assertSame('members', $cap['access_tier']);
        self::assertSame([], $cap['allowed_roles']);
        self::assertSame([], $cap['allowed_users']);
    }

    #[Test]
    public function picks_strictest_non_restricted_tier(): void
    {
        $items = [
            $this->item(AccessTier::Public, [], []),
            $this->item(AccessTier::Staff, [], []),
        ];
        $cap = SynthesisAccessCapper::cap($items);
        self::assertSame('staff', $cap['access_tier']);
        self::assertSame([], $cap['allowed_roles']);
    }

    #[Test]
    public function restricted_intersects_allowed_roles(): void
    {
        $items = [
            $this->item(AccessTier::Restricted, ['a', 'b'], []),
            $this->item(AccessTier::Restricted, ['b', 'c'], []),
        ];
        $cap = SynthesisAccessCapper::cap($items);
        self::assertSame('restricted', $cap['access_tier']);
        self::assertSame(['b'], $cap['allowed_roles']);
    }

    /**
     * @param string[] $roles
     * @param string[] $users
     */
    private function item(AccessTier $tier, array $roles, array $users): KnowledgeItem
    {
        return new KnowledgeItem([
            'title'          => 'x',
            'content'        => 'y',
            'community_id'   => 'c1',
            'knowledge_type' => KnowledgeType::Governance->value,
            'access_tier'    => $tier->value,
            'allowed_roles'  => $roles === [] ? null : json_encode($roles, JSON_THROW_ON_ERROR),
            'allowed_users'  => $users === [] ? null : json_encode($users, JSON_THROW_ON_ERROR),
        ]);
    }
}
