<?php

namespace SymfonyCasts\ObjectTranslationBundle\Dto;

final class TranslatableTypeInfo
{
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        /** @var list<string> */
        public readonly array $fields,
    ) {
    }
}
