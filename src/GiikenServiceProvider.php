<?php

declare(strict_types=1);

namespace Giiken;

use Giiken\Entity\Community\Community;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

final class GiikenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'community',
            label: 'Community',
            class: Community::class,
            keys: [
                'id'    => 'id',
                'uuid'  => 'uuid',
                'label' => 'name',
            ],
        ));

        $this->entityType(new EntityType(
            id: 'knowledge_item',
            label: 'Knowledge Item',
            class: KnowledgeItem::class,
            keys: [
                'id'    => 'id',
                'uuid'  => 'uuid',
                'label' => 'title',
            ],
        ));
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void {}
}
