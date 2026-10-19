<?php

namespace SymfonyCasts\ObjectTranslationBundle\Dto;

final class TranslationStatus
{
    /**
     * @param list<string> $translatedFields
     * @param list<string> $missingFields
     */
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $locale,
        public readonly array $translatedFields,
        public readonly array $missingFields,
    ) {
    }

    public function isFullyTranslated(): bool
    {
        return 0 === \count($this->missingFields);
    }

    public function completionRatio(): float
    {
        $total = \count($this->translatedFields) + \count($this->missingFields);

        if (0 === $total) {
            return 1.0;
        }

        return \count($this->translatedFields) / $total;
    }
}
