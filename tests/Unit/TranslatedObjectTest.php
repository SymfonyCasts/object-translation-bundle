<?php

namespace SymfonyCasts\ObjectTranslationBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymfonyCasts\ObjectTranslationBundle\TranslatedObject;

class TranslatedObjectTest extends TestCase
{
    public function testCanAccessUnderlyingObject()
    {
        $object = new TranslatedObject(new ObjectForTranslationStub(), []);

        $this->assertSame('value1', $object->prop1);
        $this->assertTrue(isset($object->prop1), 'Public property should be accessible');
        $this->assertFalse(isset($object->prop2), 'Private properties should not be accessible');
        $this->assertSame('value2', $object->prop2());
        $this->assertSame('value3', $object->getProp3());
    }

    public function testCallUsesGetterIfAvailable()
    {
        $object = new TranslatedObject(new ObjectForTranslationStub(), []);

        $this->assertSame('value3', $object->prop3());
    }

    public function testCanTranslateProperties()
    {
        $object = new TranslatedObject(new ObjectForTranslationStub(), [
            'prop1' => 'translated1',
            'prop2' => 'translated2',
            'prop3' => 'translated3',
        ]);

        $this->assertSame('translated1', $object->prop1);
        $this->assertTrue(isset($object->prop1), 'Public property should be accessible');
        $this->assertFalse(isset($object->prop2), 'Private properties should not be accessible');
        $this->assertSame('translated2', $object->prop2());
        $this->assertSame('translated3', $object->getProp3());
    }
}

class ObjectForTranslationStub
{
    public string $prop1 = 'value1';
    private string $prop2 = 'value2';
    private string $prop3 = 'value3';

    public function prop2(): string
    {
        return $this->prop2;
    }

    public function getProp3(): string
    {
        return $this->prop3;
    }
}
