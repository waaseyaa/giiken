<?php

declare(strict_types=1);

namespace App\Provider;

use App\Access\CommunityRoleResolver;
use App\Access\CommunityRoleResolverInterface;
use App\Access\KnowledgeItemAccessPolicy;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class AuthzProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(CommunityRoleResolverInterface::class, static fn (): CommunityRoleResolverInterface => new CommunityRoleResolver());
        $this->singleton(KnowledgeItemAccessPolicy::class, function (): KnowledgeItemAccessPolicy {
            return new KnowledgeItemAccessPolicy($this->resolve(CommunityRoleResolverInterface::class));
        });
    }
}
