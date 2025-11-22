<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SymfonyCasts\ObjectTranslationBundle\Command\ObjectTranslationExportCommand;
use SymfonyCasts\ObjectTranslationBundle\Command\ObjectTranslationImportCommand;
use SymfonyCasts\ObjectTranslationBundle\Command\ObjectTranslationWarmupCommand;
use SymfonyCasts\ObjectTranslationBundle\ObjectTranslator;
use SymfonyCasts\ObjectTranslationBundle\TranslatableMappingManager;
use SymfonyCasts\ObjectTranslationBundle\Twig\ObjectTranslatorExtension;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('symfonycasts.object_translator', ObjectTranslator::class)
            ->args([
                service('translation.locale_switcher'),
                param('kernel.default_locale'),
                service('.symfonycasts.object_translator.mapping_manager')
            ])
            ->tag('twig.runtime')

        ->alias(ObjectTranslator::class, 'symfonycasts.object_translator')

        ->set('.symfonycasts.object_translator.mapping_manager', TranslatableMappingManager::class)
            ->args([
                abstract_arg('Translation class'),
                service('doctrine'),
            ])

        ->set('.symfonycasts.object_translator.warmup_command', ObjectTranslationWarmupCommand::class)
            ->args([
                service('symfonycasts.object_translator'),
                service('.symfonycasts.object_translator.mapping_manager'),
                service('translation.locale_switcher'),
                param('kernel.enabled_locales'),
            ])
            ->tag('console.command')

        ->set('.symfonycasts.object_translator.export_command', ObjectTranslationExportCommand::class)
            ->args([
                service('.symfonycasts.object_translator.mapping_manager'),
            ])
            ->tag('console.command')

        ->set('.symfonycasts.object_translator.import_command', ObjectTranslationImportCommand::class)
            ->args([
                service('.symfonycasts.object_translator.mapping_manager'),
            ])
            ->tag('console.command')

        ->set('.symfonycasts.object_translator.twig_extension', ObjectTranslatorExtension::class)
            ->tag('twig.extension')
    ;
};
