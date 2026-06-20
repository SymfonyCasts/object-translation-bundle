<?php

namespace SymfonyCasts\ObjectTranslationBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use SymfonyCasts\ObjectTranslationBundle\ObjectTranslator;
use SymfonyCasts\ObjectTranslationBundle\Tests\Fixture\Entity\Translation;
use SymfonyCasts\ObjectTranslationBundle\TranslatableMappingManager;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;
use function Zenstruck\Foundry\Persistence\repository;

class TranslatableMappingManagerIntegrationTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private TranslatableMappingManager $manager;

    protected function setUp(): void
    {
        $translator = self::getContainer()->get(ObjectTranslator::class);
        $reflection = new \ReflectionClass($translator);
        $property = $reflection->getProperty('mappingManager');
        $this->manager = $property->getValue($translator);
    }

    public function testDeleteSingleField(): void
    {
        persist(Translation::class, [
            'objectType' => 'entity1',
            'objectId' => '1',
            'locale' => 'fr',
            'field' => 'property1',
            'value' => 'valeur1',
        ]);
        persist(Translation::class, [
            'objectType' => 'entity1',
            'objectId' => '1',
            'locale' => 'fr',
            'field' => 'property1_extra',
            'value' => 'valeur1_extra',
        ]);

        $this->manager->delete('entity1', '1', 'fr', 'property1');

        $repo = repository(Translation::class);
        $this->assertCount(1, $repo->findAll());
        $this->assertNull($repo->findOneBy(['field' => 'property1']));
        $this->assertNotNull($repo->findOneBy(['field' => 'property1_extra']));
    }

    public function testDeleteAllFieldsForEntityAndLocale(): void
    {
        persist(Translation::class, [
            'objectType' => 'entity1',
            'objectId' => '1',
            'locale' => 'fr',
            'field' => 'property1',
            'value' => 'valeur1',
        ]);
        persist(Translation::class, [
            'objectType' => 'entity1',
            'objectId' => '1',
            'locale' => 'fr',
            'field' => 'property1_extra',
            'value' => 'valeur1_extra',
        ]);
        persist(Translation::class, [
            'objectType' => 'entity1',
            'objectId' => '1',
            'locale' => 'en',
            'field' => 'property1',
            'value' => 'value1',
        ]);

        $this->manager->delete('entity1', '1', 'fr');

        $repo = repository(Translation::class);
        $this->assertCount(1, $repo->findAll());
        $this->assertNotNull($repo->findOneBy(['locale' => 'en']));
    }

    public function testDeleteForType(): void
    {
        persist(Translation::class, ['objectType' => 'entity1', 'objectId' => '1', 'locale' => 'fr', 'field' => 'f', 'value' => 'v']);
        persist(Translation::class, ['objectType' => 'entity1', 'objectId' => '2', 'locale' => 'fr', 'field' => 'f', 'value' => 'v']);
        persist(Translation::class, ['objectType' => 'other', 'objectId' => '1', 'locale' => 'fr', 'field' => 'f', 'value' => 'v']);

        $this->manager->deleteForType('entity1');

        $repo = repository(Translation::class);
        $this->assertCount(1, $repo->findAll());
        $this->assertNotNull($repo->findOneBy(['objectType' => 'other']));
    }

    public function testDeleteForTypeAndLocale(): void
    {
        persist(Translation::class, ['objectType' => 'entity1', 'objectId' => '1', 'locale' => 'fr', 'field' => 'f', 'value' => 'v']);
        persist(Translation::class, ['objectType' => 'entity1', 'objectId' => '1', 'locale' => 'en', 'field' => 'f', 'value' => 'v']);

        $this->manager->deleteForType('entity1', 'fr');

        $repo = repository(Translation::class);
        $this->assertCount(1, $repo->findAll());
        $this->assertNotNull($repo->findOneBy(['locale' => 'en']));
    }
}
