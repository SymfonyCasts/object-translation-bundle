<?php

namespace SymfonyCasts\ObjectTranslationBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SymfonyCasts\ObjectTranslationBundle\TranslatableMappingManager;
use SymfonyCasts\ObjectTranslationBundle\TranslationCache;

/**
 * @internal
 */
#[AsCommand(
    name: 'object-translation:import',
    description: 'Imports object translations from a CSV.',
)]
final class ObjectTranslationImportCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private TranslatableMappingManager $mappingManager,
        private TranslationCache $cache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'The CSV file to import from.')
            ->addArgument('locale', InputArgument::REQUIRED, 'The locale to import translations for.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $locale = $input->getArgument('locale');

        $io = new SymfonyStyle($input, $output);

        $io->title('Importing Object Translations');

        $fp = fopen($file, 'r');

        $io->progressStart();

        fgetcsv($fp, escape: '\\');

        $pending = [];

        while (($row = fgetcsv($fp, escape: '\\')) !== false) {
            [$type, $id, $field, $value] = $row;

            $this->mappingManager->upsert($type, $id, $locale, $field, $value);
            $pending[] = [$type, $id];

            if (\count($pending) >= self::BATCH_SIZE) {
                $this->flush($pending, $locale);
                $pending = [];
            }

            $io->progressAdvance();
        }

        $this->flush($pending, $locale);

        fclose($fp);

        $io->progressFinish();

        $io->success(sprintf('Imported "%s"', $file));

        return self::SUCCESS;
    }

    /**
     * Invalidates after flushing so a concurrent read can't cache the old values again.
     *
     * @param list<array{string, string}> $pending
     */
    private function flush(array $pending, string $locale): void
    {
        $this->mappingManager->flush();

        foreach ($pending as [$type, $id]) {
            $this->cache->invalidateObject($type, $id, $locale);
        }
    }
}
