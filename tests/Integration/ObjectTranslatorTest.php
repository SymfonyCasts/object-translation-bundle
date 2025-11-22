<?php

namespace SymfonyCasts\ObjectTranslationBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use SymfonyCasts\ObjectTranslationBundle\ObjectTranslator;
use SymfonyCasts\ObjectTranslationBundle\Tests\Fixture\Entity\Entity1;
use SymfonyCasts\ObjectTranslationBundle\Tests\Fixture\Entity\Translation;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;

class ObjectTranslatorTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testCanAccessService()
    {
        $entity = persist(Entity1::class, [
            'property1' => 'value1',
        ]);
        persist(Translation::class, [
            'objectType' => 'entity1',
            'objectId' => $entity->id,
            'locale' => 'fr',
            'field' => 'property1',
            'value' => 'translated1',
        ]);

        $translator = self::getContainer()->get(ObjectTranslator::class);

        $translated = $translator->translate($entity, 'fr');

        $this->assertSame('translated1', $translated->property1);
    }
}
