<?php

namespace SymfonyCasts\ObjectTranslationBundle\Tests\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use SymfonyCasts\ObjectTranslationBundle\Tests\Fixture\Entity\Entity1;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;

class ObjectTranslationExportCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testExport(): void
    {
        $first = persist(Entity1::class, ['property1' => 'v1']);
        $second = persist(Entity1::class, ['property1' => 'v2']);
        $file = tempnam(sys_get_temp_dir(), 'export');

        $command = (new Application(self::$kernel))->find('object-translation:export');
        $tester = new CommandTester($command);
        $tester->execute(['file' => $file]);
        $csv = file_get_contents($file);
        unlink($file);

        $tester->assertCommandIsSuccessful();
        $this->assertSame("type,id,field,value\nentity1,{$first->id},property1,v1\nentity1,{$second->id},property1,v2\n", $csv);
    }
}
