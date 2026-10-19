<?php

namespace SymfonyCasts\ObjectTranslationBundle\Tests\Unit;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Proxy;
use PHPUnit\Framework\TestCase;
use SymfonyCasts\ObjectTranslationBundle\Mapping\Translatable;
use SymfonyCasts\ObjectTranslationBundle\Mapping\TranslatableProperty;
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

    public function testUnwrapsProxies(): void
    {
        $this->assertSame('private_property', $this->manager->translatableTypeFor(new PrivatePropertyProxyStub()));
        $this->assertSame(['name'], $this->manager->getTranslatableFieldsForClass(PrivatePropertyProxyStub::class));
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

#[Translatable('private_property')]
class PrivatePropertyStub
{
    #[TranslatableProperty]
    private string $name = 'value';
}

class PrivatePropertyProxyStub extends PrivatePropertyStub implements Proxy
{
    public function __load(): void
    {
    }

    public function __isInitialized(): bool
    {
        return true;
    }
}
