<?php

namespace SymfonyCasts\ObjectTranslationBundle\Tests\Unit;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use SymfonyCasts\ObjectTranslationBundle\Tests\Fixture\Entity\Entity1;
use SymfonyCasts\ObjectTranslationBundle\TranslatableMappingManager;

class TranslatableMappingManagerTest extends TestCase
{
    private ManagerRegistry $doctrine;
    private TranslatableMappingManager $manager;

    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->manager = new TranslatableMappingManager(
            'App\Entity\Translation',
            $this->doctrine
        );
    }

    public function testGetTranslatableFieldsForClass(): void
    {
        $fields = $this->manager->getTranslatableFieldsForClass(Entity1::class);
        $this->assertEquals(['property1'], $fields);
    }

    public function testGetTranslatableTypeMap(): void
    {
        $this->doctrine->method('getManagers')->willReturn([]);

        // This test might be more complex because allTranslatableObjects() iterates over managers
        // and metadata. Let's see if we can mock that easily or if it's better in Integration test.
        $map = $this->manager->getTranslatableTypeMap();
        $this->assertIsArray($map);
    }
}
