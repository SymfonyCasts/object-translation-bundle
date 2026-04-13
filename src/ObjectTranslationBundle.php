<?php

namespace SymfonyCasts\ObjectTranslationBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use SymfonyCasts\ObjectTranslationBundle\Model\Translation;

final class ObjectTranslationBundle extends AbstractBundle
{
    protected string $extensionAlias = 'symfonycasts_object_translation';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('translation_class')
                    ->info('The class name of your Translation entity.')
                    ->example('App\Entity\Translation')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(fn ($v) => !is_a($v, Translation::class, true))
                        ->thenInvalid('The translation class %s must extend SymfonyCasts\ObjectTranslation\Model\Translation.')
                    ->end()
                ->end()
                ->arrayNode('cache')
                    ->info('Cache settings for object translations.')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('pool')
                            ->info('The cache pool to use for storing object translations.')
                            ->defaultValue('cache.app')
                        ->end()
                        ->integerNode('ttl')
                            ->info('The time-to-live for cached translations, in seconds. null for no expiration.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(
            DoctrineOrmMappingsPass::createXmlMappingDriver(
                [__DIR__.'/../config/doctrine/mapping' => 'SymfonyCasts\ObjectTranslationBundle\Model']
            )
        );
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $objectTranslatorDef = $builder->getDefinition('symfonycasts.object_translator');
        $translationManagerDef = $builder->getDefinition('symfonycasts.object_translation_manager');

        if ($config['cache']['enabled']) {
            $builder->setAlias('.symfonycasts.object_translator.cache', $config['cache']['pool']);
            $objectTranslatorDef->setArgument(4, $config['cache']['ttl']);
        }

        $builder->getDefinition('.symfonycasts.object_translator.mapping_manager')
            ->setArgument(0, $config['translation_class']);

        $translationManagerDef->setArgument(2, $config['translation_class']);
    }
}
