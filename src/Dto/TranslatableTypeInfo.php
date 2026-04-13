<?php

namespace SymfonyCasts\ObjectTranslationBundle\Dto;

final class TranslatableTypeInfo
{
    public function __construct(
        public readonly string $name,    // alias from #[Translatable(name: '...')]
        public readonly string $class,   // FQCN
        /** @var list<string> */
        public readonly array $fields,   // translatable property names
    ) {
    }
}
