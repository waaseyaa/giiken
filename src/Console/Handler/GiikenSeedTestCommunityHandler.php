<?php

declare(strict_types=1);

namespace App\Console\Handler;

use App\Entity\Community\Community;
use App\Entity\Community\CommunityRepositoryInterface;
use App\Entity\Community\WikiSchema;
use App\Entity\KnowledgeItem\AccessTier;
use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use App\Entity\KnowledgeItem\KnowledgeType;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\User\User;

final class GiikenSeedTestCommunityHandler
{
    public function __construct(
        private readonly CommunityRepositoryInterface $communityRepo,
        private readonly KnowledgeItemRepositoryInterface $itemRepo,
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function execute(CliIO $io): int
    {
        $existing = $this->communityRepo->findBySlug('test-community');
        if ($existing !== null) {
            $io->writeln('Community "test-community" already exists. Ensuring sample items only.');
            $community = $existing;
        } else {
            $wiki = new WikiSchema(
                defaultLanguage: 'en',
                knowledgeTypes: array_map(
                    static fn (KnowledgeType $t): string => $t->value,
                    KnowledgeType::cases(),
                ),
                llmInstructions: 'Preserve community voice; cite sources; flag uncertainty.',
            );

            $community = Community::make([
                'uuid'        => self::uuidV4(),
                'name'        => 'Test Community',
                'bundle'      => 'community',
                'slug'        => 'test-community',
                'wiki_schema' => $wiki->toArray(),
            ]);
            $community->enforceIsNew(true);
            $this->communityRepo->save($community);

            $community = $this->communityRepo->findBySlug('test-community');
            if ($community === null) {
                $io->error('Failed to load community after save.');

                return 1;
            }
            $io->writeln('Created community "test-community".');
        }

        $communityId = (string) $community->get('id');
        $this->ensureStaffUser($communityId, $io);

        $items = $this->itemRepo->findByCommunity($communityId);
        if ($items !== []) {
            $io->writeln(sprintf('Community already has %d knowledge items. Skip seeding items.', \count($items)));

            return 0;
        }

        $samples = [
            [
                'title'   => 'Welcome to Giiken',
                'content' => 'This is a seeded knowledge item for local development. Replace with real community knowledge.',
                'type'    => KnowledgeType::Cultural,
            ],
            [
                'title'   => 'Governance overview',
                'content' => 'Sample governance note. Public tier so it appears for anonymous visitors in development.',
                'type'    => KnowledgeType::Governance,
            ],
            [
                'title'   => 'Land and territory',
                'content' => 'Sample land reference. Use the ingestion pipeline to import real documents.',
                'type'    => KnowledgeType::Land,
            ],
        ];

        foreach ($samples as $row) {
            $item = KnowledgeItem::make([
                'uuid'           => self::uuidV4(),
                'title'          => $row['title'],
                'bundle'         => 'knowledge_item',
                'content'        => $row['content'],
                'community_id'   => $communityId,
                'knowledge_type' => $row['type']->value,
                'access_tier'    => AccessTier::Public->value,
            ]);
            $item->enforceIsNew(true);
            $this->itemRepo->save($item);
        }

        $io->writeln(sprintf('Seeded %d sample knowledge items.', \count($samples)));

        return 0;
    }

    private function ensureStaffUser(string $communityId, CliIO $io): void
    {
        $storage = $this->entityTypeManager->getStorage('user');
        $role = 'giiken.community.' . $communityId . '.staff';
        $password = getenv('GIIKEN_SEED_STAFF_PASSWORD');
        if (!\is_string($password) || $password === '') {
            $password = 'giiken-dev';
        }

        $ids = $storage->getQuery()
            ->condition('name', 'giiken_staff')
            ->range(0, 1)
            ->execute();

        if ($ids !== []) {
            $loaded = $storage->load(reset($ids));
            if (!$loaded instanceof User) {
                return;
            }
            if (!\in_array($role, $loaded->getRoles(), true)) {
                $loaded->addRole($role);
                $io->writeln('Added community staff role to user "giiken_staff".');
            }
            $loaded->setRawPassword($password);
            $storage->save($loaded);

            return;
        }

        $user = new User([
            'name'           => 'giiken_staff',
            'mail'           => 'staff@giiken.local',
            'status'         => 1,
            'email_verified' => 1,
            'roles'          => ['authenticated', $role],
        ]);
        $user->setRawPassword($password);
        $user->enforceIsNew();
        $storage->save($user);

        $io->writeln('Created user "giiken_staff" with community staff role.');
        $io->writeln('Password: GIIKEN_SEED_STAFF_PASSWORD env or default "giiken-dev".');
    }

    /** RFC 4122 version 4 (random); avoids Symfony UID in CLI handler path */
    private static function uuidV4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);

        return strtolower(sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($b, 0, 4)),
            bin2hex(substr($b, 4, 2)),
            bin2hex(substr($b, 6, 2)),
            bin2hex(substr($b, 8, 2)),
            bin2hex(substr($b, 10)),
        ));
    }
}
