<?php

namespace SymfonyCasts\ObjectTranslationBundle;

use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
final class TranslationCache
{
    private CacheInterface $cache;

    /**
     * @param list<string> $locales used to find the keys to delete when the pool doesn't support tags
     */
    public function __construct(
        ?CacheInterface $cache = null,
        private ?int $ttl = null,
        private array $locales = [],
    ) {
        $this->cache = $cache ?? new NullAdapter();
    }

    /**
     * @param callable():array<string, string> $loader
     *
     * @return array<string, string>
     */
    public function get(string $locale, string $type, string $id, callable $loader, bool $forceRefresh = false): array
    {
        return $this->cache->get(
            self::key($locale, $type, $id),
            function (ItemInterface $item) use ($type, $id, $loader) {
                if ($this->cache instanceof TagAwareCacheInterface) {
                    $item->tag([
                        'object-translation',
                        "object-translation-{$type}",
                        "object-translation-{$type}-{$id}",
                    ]);
                }

                if ($this->ttl) {
                    $item->expiresAfter($this->ttl);
                }

                return $loader();
            },
            $forceRefresh ? \INF : null,
        );
    }

    public function invalidateObject(string $type, string $id, ?string $locale = null): void
    {
        if ($locale) {
            $this->cache->delete(self::key($locale, $type, $id));

            return;
        }

        if ($this->invalidateTag("object-translation-{$type}-{$id}")) {
            return;
        }

        foreach ($this->locales as $locale) {
            $this->cache->delete(self::key($locale, $type, $id));
        }
    }

    /**
     * @param iterable<string> $ids only iterated when the pool doesn't support tags
     */
    public function invalidateType(string $type, iterable $ids, ?string $locale = null): void
    {
        if ($this->invalidateTag("object-translation-{$type}")) {
            return;
        }

        foreach ($ids as $id) {
            $this->invalidateObject($type, $id, $locale);
        }
    }

    /**
     * @param iterable<string, iterable<string>> $idsByType only iterated when the pool doesn't support tags
     */
    public function invalidateAll(iterable $idsByType): void
    {
        if ($this->invalidateTag('object-translation')) {
            return;
        }

        foreach ($idsByType as $type => $ids) {
            $this->invalidateType($type, $ids);
        }
    }

    private function invalidateTag(string $tag): bool
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return false;
        }

        return $this->cache->invalidateTags([$tag]);
    }

    private static function key(string $locale, string $type, string $id): string
    {
        return "object_translation.{$locale}.{$type}.{$id}";
    }
}
