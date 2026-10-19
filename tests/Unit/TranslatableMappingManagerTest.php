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

    public function testTranslatableFieldsFor(): void
    {
        $fields = $this->manager->translatableFieldsFor(Entity1::class);
        $this->assertEquals(['property1'], $fields);
    }

    public function testUnwrapsProxies(): void
    {
        $this->assertSame('private_property', $this->manager->translatableTypeFor(new PrivatePropertyProxyStub()));
        $this->assertSame(['name'], $this->manager->translatableFieldsFor(PrivatePropertyProxyStub::class));
    }

    public function testTranslatableTypeForClass(): void
    {
        $this->assertSame('entity1', $this->manager->translatableTypeFor(Entity1::class));
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
