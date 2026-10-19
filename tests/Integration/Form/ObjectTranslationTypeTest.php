<?php

namespace SymfonyCasts\ObjectTranslationBundle\Tests\Integration\Form;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use SymfonyCasts\ObjectTranslationBundle\Form\ObjectTranslationType;
use SymfonyCasts\ObjectTranslationBundle\Tests\Fixture\Entity\Entity1;
use SymfonyCasts\ObjectTranslationBundle\TranslationManager;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;

class ObjectTranslationTypeTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testBuildsFieldPerTranslatableProperty(): void
    {
        $entity = persist(Entity1::class, ['property1' => 'v1']);

        $form = $this->createForm($entity, 'de');
        $field = $form->get('property1')->getConfig();

        $this->assertSame(['property1'], array_keys($form->all()));
        $this->assertInstanceOf(TextType::class, $field->getType()->getInnerType());
        $this->assertFalse($field->getRequired());
        $this->assertSame('v1', $field->getOption('help'));
    }

    public function testPrefillsExistingTranslation(): void
    {
        $entity = persist(Entity1::class, ['property1' => 'v1']);
        $this->manager()->saveTranslation($entity, 'de', 'property1', 'de_v1');

        $this->assertSame('de_v1', $this->createForm($entity, 'de')->get('property1')->getData());
        $this->assertNull($this->createForm($entity, 'fr')->get('property1')->getData());
    }

    public function testSubmittedDataSavesAndDeletesTranslations(): void
    {
        $entity = persist(Entity1::class, ['property1' => 'v1']);

        $form = $this->createForm($entity, 'fr');
        $form->submit(['property1' => 'fr_v1']);
        $this->manager()->saveTranslationsFor($entity, 'fr', $form->getData());

        $this->assertSame('fr_v1', $this->manager()->findTranslation($entity, 'fr', 'property1'));

        $form = $this->createForm($entity, 'fr');
        $form->submit(['property1' => '']);
        $this->manager()->saveTranslationsFor($entity, 'fr', $form->getData());

        $this->assertNull($this->manager()->findTranslation($entity, 'fr', 'property1'));
    }

    private function createForm(object $object, string $locale): FormInterface
    {
        return self::getContainer()->get('test.form_factory')->create(ObjectTranslationType::class, options: [
            'object' => $object,
            'locale' => $locale,
        ]);
    }

    private function manager(): TranslationManager
    {
        return self::getContainer()->get('test.translation_manager');
    }
}
