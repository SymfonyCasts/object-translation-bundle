<?php

namespace SymfonyCasts\ObjectTranslationBundle\Dto;

final class TranslatableTypeInfo
{
    /**
     * @param class-string $class
     * @param list<string> $fields
     */
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly array $fields,
    ) {
    }
}
