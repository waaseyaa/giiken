<?php

declare(strict_types=1);

namespace Giiken\Console;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\Community\WikiSchema;
use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Symfony\Component\Console\Attribute\AsCommand;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\User\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'giiken:seed:test-community',
    description: 'Seed a demo community (slug test-community) with sample public knowledge items',
)]
final class SeedTestCommunityCommand extends Command
{
    public function __construct(
        private readonly CommunityRepositoryInterface $communityRepo,
        private readonly KnowledgeItemRepositoryInterface $itemRepo,
        private readonly EntityTypeManager $entityTypeManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $existing = $this->communityRepo->findBySlug('test-community');
        if ($existing !== null) {
            $output->writeln('<comment>Community "test-community" already exists. Ensuring sample items only.</comment>');
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
                'uuid'        => Uuid::v4()->toRfc4122(),
                'name'        => 'Test Community',
                'bundle'      => 'community',
                'slug'        => 'test-community',
                'wiki_schema' => $wiki->toArray(),
            ]);
            $community->enforceIsNew(true);
            $this->communityRepo->save($community);

            $community = $this->communityRepo->findBySlug('test-community');
            if ($community === null) {
                $output->writeln('<error>Failed to load community after save.</error>');

                return Command::FAILURE;
            }
            $output->writeln('<info>Created community "test-community".</info>');
        }

        $communityId = (string) $community->get('id');
        $this->ensureStaffUser($communityId, $output);

        $items = $this->itemRepo->findByCommunity($communityId);
        if ($items !== []) {
            $output->writeln(sprintf('<comment>Community already has %d knowledge items. Skip seeding items.</comment>', count($items)));

            return Command::SUCCESS;
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
                'uuid'          => Uuid::v4()->toRfc4122(),
                'title'         => $row['title'],
                'bundle'        => 'knowledge_item',
                'content'       => $row['content'],
                'community_id'  => $communityId,
                'knowledge_type'=> $row['type']->value,
                'access_tier'   => AccessTier::Public->value,
            ]);
            $item->enforceIsNew(true);
            $this->itemRepo->save($item);
        }

        $output->writeln(sprintf('<info>Seeded %d sample knowledge items.</info>', count($samples)));

        return Command::SUCCESS;
    }

    private function ensureStaffUser(string $communityId, OutputInterface $output): void
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
                $storage->save($loaded);
                $output->writeln('<info>Added community staff role to user "giiken_staff".</info>');
            }

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

        $output->writeln('<info>Created user "giiken_staff" with community staff role.</info>');
        $output->writeln('<comment>Password: GIIKEN_SEED_STAFF_PASSWORD env or default "giiken-dev".</comment>');
    }
}
