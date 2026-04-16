<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ingestion\NorthCloud;

use App\Ingestion\NorthCloud\NcHitToKnowledgeItemMapper;
use App\Tests\Integration\Support\AppKernelIntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Waaseyaa\NorthCloud\Sync\MapperRegistry;

final class NorthCloudMapperRegistrationTest extends AppKernelIntegrationTestCase
{
    #[Test]
    public function giiken_mapper_is_registered_in_northcloud_registry(): void
    {
        $registry = self::giikenProvider()->resolve(MapperRegistry::class);
        self::assertInstanceOf(MapperRegistry::class, $registry);

        $registeredGiikenMappers = array_values(array_filter(
            $registry->all(),
            static fn (object $mapper): bool => $mapper instanceof NcHitToKnowledgeItemMapper,
        ));

        self::assertCount(
            1,
            $registeredGiikenMappers,
            'NcHitToKnowledgeItemMapper should be registered exactly once in the package MapperRegistry.',
        );
    }
}
