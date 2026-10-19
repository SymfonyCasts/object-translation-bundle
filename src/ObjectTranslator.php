<?php

namespace SymfonyCasts\ObjectTranslationBundle;

use Symfony\Contracts\Translation\LocaleAwareInterface;

final class ObjectTranslator
{
    public function __construct(
        private LocaleAwareInterface $localeAware,
        private string $defaultLocale,
        private TranslatableMappingManager $mappingManager,
        private TranslationCache $cache,
    ) {
    }

    /**
     * @template T of object
     *
     * @param T $object
     *
     * @return T
     */
    public function translate(object $object, ?string $locale = null, array $options = []): object
    {
        $locale = $locale ?? $this->localeAware->getLocale();

        if ($this->defaultLocale === $locale) {
            return $object;
        }

        return new TranslatedObject($object, $this->translationsFor(
            $object,
            $locale,
            $options['force_refresh'] ?? false,
        ));
    }

    private function translationsFor(object $object, string $locale, bool $forceRefresh): array
    {
        $type = $this->mappingManager->translatableTypeFor($object);

        $id = $this->mappingManager->idFor($object);

        return $this->cache->get(
            $locale,
            $type,
            $id,
            fn () => $this->mappingManager->translationsFor($locale, $type, $id),
            $forceRefresh,
        );
    }
}
