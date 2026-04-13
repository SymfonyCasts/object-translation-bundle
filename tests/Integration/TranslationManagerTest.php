<?php

namespace SymfonyCasts\ObjectTranslationBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use SymfonyCasts\ObjectTranslationBundle\Dto\TranslatableTypeInfo;
use SymfonyCasts\ObjectTranslationBundle\Dto\TranslationStatus;
use SymfonyCasts\ObjectTranslationBundle\ObjectTranslator;
use SymfonyCasts\ObjectTranslationBundle\TranslationManagerInterface;
use SymfonyCasts\ObjectTranslationBundle\Tests\Fixture\Entity\Entity1;
use SymfonyCasts\ObjectTranslationBundle\Tests\Fixture\Entity\Translation;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;
use function Zenstruck\Foundry\Persistence\repository;

class TranslationManagerTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private TranslationManagerInterface $manager;

    protected function setUp(): void
    {
        $this->manager = self::getContainer()->get(TranslationManagerInterface::class);
    }

    public function testGetTranslatableTypes(): void
    {
        $types = $this->manager->getTranslatableTypes();
        
        $this->assertCount(1, $types);
        $this->assertInstanceOf(TranslatableTypeInfo::class, $types[0]);
        $this->assertSame('entity1', $types[0]->name);
        $this->assertSame(Entity1::class, $types[0]->class);
        $this->assertSame(['property1'], $types[0]->fields);
    }

    public function testGetTranslatableFields(): void
    {
        $fields = $this->manager->getTranslatableFields(Entity1::class);
        $this->assertSame(['property1'], $fields);
    }

    public function testCRUDTranslations(): void
    {
        $entity = persist(Entity1::class, ['property1' => 'v1']);

        // Save
        $this->manager->saveTranslation($entity, 'de', 'property1', 'de_v1');
        
        // Find
        $this->assertSame('de_v1', $this->manager->findTranslation($entity, 'de', 'property1'));
        $this->assertSame(['property1' => 'de_v1'], $this->manager->findTranslations($entity, 'de'));

        // Status
        $status = $this->manager->getTranslationStatus($entity, 'de');
        $this->assertInstanceOf(TranslationStatus::class, $status);
        $this->assertSame(['property1'], $status->translatedFields);
        $this->assertEmpty($status->missingFields);
        $this->assertTrue($status->isFullyTranslated());
        $this->assertEquals(1.0, $status->getCompletionPercentage());

        // Delete
        $this->manager->deleteTranslation($entity, 'de', 'property1');
        $this->assertNull($this->manager->findTranslation($entity, 'de', 'property1'));
    }

    public function testInvalidateCache(): void
    {
        $entity = persist(Entity1::class, ['property1' => 'v1']);
        persist(Translation::class, [
            'objectType' => 'entity1',
            'objectId' => $entity->id,
            'locale' => 'de',
            'field' => 'property1',
            'value' => 'old_value',
        ]);

        $translator = self::getContainer()->get(ObjectTranslator::class);
        
        // Prime cache
        $translated = $translator->translate($entity, 'de');
        $this->assertSame('old_value', $translated->property1);

        // Update translation directly in DB to bypass manager auto-invalidation
        $t = repository(Translation::class)->findOneBy(['locale' => 'de']);
        $t->value = 'new_value';
        self::getContainer()->get('doctrine')->getManager()->flush();

        // Still old value due to cache
        $translated = $translator->translate($entity, 'de');
        $this->assertSame('old_value', $translated->property1);

        // Invalidate
        $this->manager->invalidateCacheForEntity($entity);

        // Now should be new value
        $translated = $translator->translate($entity, 'de');
        $this->assertSame('new_value', $translated->property1);
    }
}
