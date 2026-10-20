<?php

namespace SymfonyCasts\ObjectTranslationBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use SymfonyCasts\ObjectTranslationBundle\TranslatableMappingManager;
use SymfonyCasts\ObjectTranslationBundle\TranslationManager;

/**
 * Data is an array of field => translated value, null for an empty field.
 * Pass it to {@see TranslationManager::saveTranslationsFor()}.
 */
final class ObjectTranslationType extends AbstractType
{
    public function __construct(
        private TranslationManager $manager,
        private TranslatableMappingManager $mappingManager,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $object = $options['object'];
        $formFactory = $builder->getFormFactory();

        // guessing picks TextType or TextareaType from the Doctrine column type
        foreach ($this->mappingManager->translatableValuesFor($object) as $field => $originalValue) {
            $builder->add($formFactory->createBuilderForProperty($object::class, $field, null, [
                'required' => false,
                'help' => $originalValue,
            ]));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['object', 'locale'])
            ->setAllowedTypes('object', 'object')
            ->setAllowedTypes('locale', 'string')
            ->setDefaults([
                'data' => fn (Options $options) => $this->manager->findTranslationsFor($options['object'], $options['locale']),
                // the help text is the untranslated value, not a translation key
                'translation_domain' => false,
            ])
        ;
    }
}
