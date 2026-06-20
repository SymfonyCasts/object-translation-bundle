<?php

namespace SymfonyCasts\ObjectTranslationBundle;

use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

final class ObjectTranslator
{
    private CacheInterface $cache;

    public function __construct(
        private LocaleAwareInterface $localeAware,
        private string $defaultLocale,
        private TranslatableMappingManager $mappingManager,
        ?CacheInterface $cache = null,
        private ?int $cacheTtl = null,
    ) {
        $this->cache = $cache ?? new NullAdapter();
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
            "object_translation.{$locale}.{$type}.{$id}",
            function (ItemInterface $item) use ($locale, $type, $id) {
                if ($this->cache instanceof TagAwareCacheInterface) {
                    $item->tag([
                        'object-translation',
                        "object-translation-{$type}",
                        "object-translation-{$type}-{$id}",
                    ]);
                }

                if ($this->cacheTtl) {
                    $item->expiresAfter($this->cacheTtl);
                }

                return $this->mappingManager->translationsFor($locale, $type, $id);
            },
            $forceRefresh ? \INF : null,
        );
    }
}
