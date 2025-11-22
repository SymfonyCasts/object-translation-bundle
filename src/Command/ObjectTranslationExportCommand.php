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
    name: 'object-translation:export',
    description: 'Exports object translations to a CSV.',
)]
final class ObjectTranslationExportCommand extends Command
{
    public function __construct(
        private TranslatableMappingManager $mappingManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'The CSV file to export to.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');

        $io->title('Exporting Object Translations to CSV');

        $fp = fopen($file, 'w');

        fputcsv($fp, [
            'type',
            'id',
            'field',
            'value',
        ]);

        foreach ($io->progressIterate($this->mappingManager->allTranslatableObjects()) as $object) {
            $type = $this->mappingManager->translatableTypeFor($object);
            $id = $this->mappingManager->idFor($object);

            foreach ($this->mappingManager->translatableValuesFor($object) as $field => $value) {
                fputcsv($fp, [
                    $type,
                    $id,
                    $field,
                    $value,
                ]);
            }
        }

        fclose($fp);

        $io->success(sprintf('Exported to "%s"', $file));

        return self::SUCCESS;
    }
}
