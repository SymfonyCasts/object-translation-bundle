<?php

namespace SymfonyCasts\ObjectTranslationBundle\Dto;

final class TranslationStatus
{
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $locale,
        /** @var list<string> */
        public readonly array $translatedFields,
        /** @var list<string> */
        public readonly array $missingFields,
    ) {
    }

    public function isFullyTranslated(): bool
    {
        return 0 === \count($this->missingFields);
    }

    public function getCompletionPercentage(): float
    {
        $total = \count($this->translatedFields) + \count($this->missingFields);

        if (0 === $total) {
            return 1.0;
        }

        return \count($this->translatedFields) / $total;
    }
}
