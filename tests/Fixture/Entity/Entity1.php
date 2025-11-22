<?php

namespace SymfonyCasts\ObjectTranslationBundle\Tests\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;
use SymfonyCasts\ObjectTranslationBundle\Mapping\Translatable;
use SymfonyCasts\ObjectTranslationBundle\Mapping\TranslatableProperty;

#[Translatable('entity1')]
#[ORM\Entity]
class Entity1
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    #[ORM\Column]
    #[TranslatableProperty]
    public string $property1;
}
