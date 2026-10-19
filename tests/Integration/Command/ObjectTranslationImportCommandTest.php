<?php

namespace SymfonyCasts\ObjectTranslationBundle\Tests\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use SymfonyCasts\ObjectTranslationBundle\ObjectTranslator;
use SymfonyCasts\ObjectTranslationBundle\Tests\Fixture\Entity\Entity1;
use SymfonyCasts\ObjectTranslationBundle\Tests\Fixture\Entity\Translation;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;
use function Zenstruck\Foundry\Persistence\repository;

class ObjectTranslationImportCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testImport(): void
    {
        $first = persist(Entity1::class, ['property1' => 'v1']);
        $second = persist(Entity1::class, ['property1' => 'v2']);
        persist(Translation::class, [
            'objectType' => 'entity1',
            'objectId' => $first->id,
            'locale' => 'de',
            'field' => 'property1',
            'value' => 'old',
        ]);

        $translator = self::getContainer()->get(ObjectTranslator::class);
        $this->assertSame('old', $translator->translate($first, 'de')->property1);

        $file = tempnam(sys_get_temp_dir(), 'import');
        file_put_contents($file, "type,id,field,value\nentity1,{$first->id},property1,de_v1\nentity1,{$second->id},property1,de_v2\n");

        $command = (new Application(self::$kernel))->find('object-translation:import');
        $tester = new CommandTester($command);
        $tester->execute(['file' => $file, 'locale' => 'de']);
        unlink($file);

        $tester->assertCommandIsSuccessful();
        $this->assertCount(2, repository(Translation::class)->findAll());
        $this->assertSame('de_v1', $translator->translate($first, 'de')->property1);
        $this->assertSame('de_v2', $translator->translate($second, 'de')->property1);
    }
}
