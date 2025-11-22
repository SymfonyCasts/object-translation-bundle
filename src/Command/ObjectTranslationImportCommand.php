<?php

namespace SymfonyCasts\ObjectTranslationBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SymfonyCasts\ObjectTranslationBundle\TranslatableMappingManager;

/**
 * @internal
 */
#[AsCommand(
    name: 'object-translation:import',
    description: 'Imports object translations from a CSV.',
)]
final class ObjectTranslationImportCommand extends Command
{
    public function __construct(
        private TranslatableMappingManager $mappingManager,
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

        fgetcsv($fp);

        while (($row = fgetcsv($fp)) !== false) {
            [$type, $id, $field, $value] = $row;

            $this->mappingManager->upsert($type, $id, $locale, $field, $value);

            $io->progressAdvance();
        }

        fclose($fp);

        $io->progressFinish();

        $io->success(sprintf('Imported "%s"', $file));

        return self::SUCCESS;
    }
}
