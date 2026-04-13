<?php

namespace SymfonyCasts\ObjectTranslationBundle;

/**
 * Public API for managing translations programmatically.
 */
interface TranslationManagerInterface
{
    // --- Discovery (class-level) ---

    /** @return list<Dto\TranslatableTypeInfo> */
    public function getTranslatableTypes(): array;

    /**
     * @param class-string $class e.g. Product::class
     *
     * @return list<string>
     */
    public function getTranslatableFields(string $class): array;

    /**
     * @param class-string $class e.g. Product::class
     *
     * @return iterable<object>
     */
    public function getObjectsForType(string $class): iterable;

    // --- Read (entity-level) ---

    public function findTranslation(object $entity, string $locale, string $field): ?string;

    /** @return array<string, string> field => translated value */
    public function findTranslations(object $entity, string $locale): array;

    // --- Write (entity-level) ---

    public function saveTranslation(object $entity, string $locale, string $field, string $value): void;

    /**
     * @param array<string, string> $fields field => value
     */
    public function saveTranslations(object $entity, string $locale, array $fields): void;

    // --- Delete ---

    /** null $field = delete all fields for this entity+locale */
    public function deleteTranslation(object $entity, string $locale, ?string $field = null): void;

    /**
     * Delete all translations for a class; optionally scoped to a locale.
     *
     * @param class-string $class e.g. Product::class
     */
    public function deleteTranslationsForType(string $class, ?string $locale = null): void;

    // --- Status (entity-level) ---

    public function getTranslationStatus(object $entity, string $locale): Dto\TranslationStatus;

    // --- Cache ---

    public function invalidateCacheForEntity(object $entity): void;

    /** @param class-string $class e.g. Product::class */
    public function invalidateCacheForType(string $class): void;

    public function invalidateAllTranslationCache(): void;
}
