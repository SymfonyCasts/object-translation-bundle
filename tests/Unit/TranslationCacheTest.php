<?php

namespace SymfonyCasts\ObjectTranslationBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use SymfonyCasts\ObjectTranslationBundle\TranslationCache;

class TranslationCacheTest extends TestCase
{
    public function testInvalidateObjectForLocale(): void
    {
        $cache = $this->primedCache(new TranslationCache(new ArrayAdapter(), locales: ['de', 'fr']));

        $cache->invalidateObject('product', '1', 'de');

        $this->assertSame(['new', 'old', 'old'], $this->cachedValues($cache));
    }

    public function testInvalidateObjectFallsBackToLocales(): void
    {
        $cache = $this->primedCache(new TranslationCache(new ArrayAdapter(), locales: ['de', 'fr']));

        $cache->invalidateObject('product', '1');

        $this->assertSame(['new', 'new', 'old'], $this->cachedValues($cache));
    }

    public function testInvalidateTypeFallsBackToIds(): void
    {
        $cache = $this->primedCache(new TranslationCache(new ArrayAdapter(), locales: ['de', 'fr']));

        $cache->invalidateType('product', ['1', '2'], 'fr');

        $this->assertSame(['old', 'new', 'new'], $this->cachedValues($cache));
    }

    public function testInvalidateAllFallsBackToIdsByType(): void
    {
        $cache = $this->primedCache(new TranslationCache(new ArrayAdapter(), locales: ['de', 'fr']));

        $cache->invalidateAll(['product' => ['1', '2']]);

        $this->assertSame(['new', 'new', 'new'], $this->cachedValues($cache));
    }

    public function testTagsAreUsedWhenSupported(): void
    {
        $cache = $this->primedCache(new TranslationCache(new TagAwareAdapter(new ArrayAdapter())));

        $cache->invalidateType('product', (function () {
            throw new \LogicException('IDs should not be loaded.');
            yield;
        })());

        $this->assertSame(['new', 'new', 'new'], $this->cachedValues($cache));
    }

    private function primedCache(TranslationCache $cache): TranslationCache
    {
        $this->cachedValues($cache, 'old');

        return $cache;
    }

    /**
     * @return list<string>
     */
    private function cachedValues(TranslationCache $cache, string $loaded = 'new'): array
    {
        return [
            $cache->get('de', 'product', '1', fn () => ['name' => $loaded])['name'],
            $cache->get('fr', 'product', '1', fn () => ['name' => $loaded])['name'],
            $cache->get('fr', 'product', '2', fn () => ['name' => $loaded])['name'],
        ];
    }
}
