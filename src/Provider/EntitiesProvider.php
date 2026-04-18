<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\Community\Community;
use App\Entity\Community\CommunityRepository;
use App\Entity\Community\CommunityRepositoryInterface;
use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Entity\KnowledgeItem\KnowledgeItemRepository;
use App\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use App\Wiki\WikiLintReport;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository as WaaseyaaEntityRepository;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class EntitiesProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'community',
            label: 'Community',
            class: Community::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'label' => 'name',
            ],
        ));

        $this->entityType(new EntityType(
            id: 'knowledge_item',
            label: 'Knowledge Item',
            class: KnowledgeItem::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'label' => 'title',
            ],
        ));

        $this->entityType(new EntityType(
            id: 'wiki_lint_report',
            label: 'Wiki Lint Report',
            class: WikiLintReport::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'label' => 'title',
            ],
        ));

        $this->singleton(CommunityRepositoryInterface::class, function (): CommunityRepositoryInterface {
            $etm = $this->resolve(EntityTypeManager::class);
            $database = $this->resolve(DatabaseInterface::class);
            $dispatcher = $this->resolve(PsrEventDispatcherInterface::class);
            $driver = new SqlStorageDriver(new SingleConnectionResolver($database), 'id');
            $entityRepo = new WaaseyaaEntityRepository(
                $etm->getDefinition('community'),
                $driver,
                $dispatcher,
                revisionDriver: null,
                database: $database,
            );

            return new CommunityRepository($entityRepo);
        });

        $this->singleton(KnowledgeItemRepositoryInterface::class, function (): KnowledgeItemRepositoryInterface {
            $etm = $this->resolve(EntityTypeManager::class);
            $database = $this->resolve(DatabaseInterface::class);
            $dispatcher = $this->resolve(PsrEventDispatcherInterface::class);
            $driver = new SqlStorageDriver(new SingleConnectionResolver($database), 'id');
            $entityRepo = new WaaseyaaEntityRepository(
                $etm->getDefinition('knowledge_item'),
                $driver,
                $dispatcher,
                revisionDriver: null,
                database: $database,
            );

            return new KnowledgeItemRepository($entityRepo);
        });
    }
}
