<?php

declare(strict_types=1);

namespace App\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Search\SearchIndexableInterface;
use Waaseyaa\Search\SearchIndexerInterface;

#[AsCommand(
    name: 'search:reindex',
    description: 'Rebuild the search index from all indexable entities',
)]
final class SearchReindexCommand extends Command
{
    private const DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        private readonly SearchIndexerInterface $indexer,
        private readonly EntityTypeManager $entityTypeManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'batch-size',
            'b',
            InputOption::VALUE_REQUIRED,
            'Progress log batch size',
            (string) self::DEFAULT_BATCH_SIZE,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchSize = max(1, (int) $input->getOption('batch-size'));

        $output->writeln('<info>Clearing search index...</info>');
        $this->indexer->removeAll();

        $totalIndexed = 0;

        foreach ($this->entityTypeManager->getDefinitions() as $entityType) {
            $storage = $this->entityTypeManager->getStorage($entityType->id());
            $entities = $storage->loadMultiple();
            $typeIndexed = 0;
            $batchCount = 0;

            foreach ($entities as $entity) {
                if (!$entity instanceof SearchIndexableInterface) {
                    continue;
                }

                $this->indexer->index($entity);
                $typeIndexed++;
                $totalIndexed++;
                $batchCount++;

                if ($batchCount >= $batchSize) {
                    $output->writeln(sprintf('  [%s] Indexed %d entities...', $entityType->id(), $typeIndexed));
                    $batchCount = 0;
                }
            }

            if ($typeIndexed > 0) {
                $output->writeln(sprintf('<comment>%s</comment>: indexed %d entities', $entityType->id(), $typeIndexed));
            }
        }

        $output->writeln(sprintf('<info>Reindex complete. %d documents indexed.</info>', $totalIndexed));
        $output->writeln('<info>Schema version: ' . $this->indexer->getSchemaVersion() . '</info>');

        return Command::SUCCESS;
    }
}

