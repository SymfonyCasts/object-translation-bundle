<?php

namespace SymfonyCasts\ObjectTranslationBundle;

use SymfonyCasts\ObjectTranslationBundle\Dto\TranslatableTypeInfo;
use SymfonyCasts\ObjectTranslationBundle\Dto\TranslationStatus;

final class TranslationManager
{
    public function __construct(
        private TranslatableMappingManager $mappingManager,
        private TranslationCache $cache,
    ) {
    }

    /**
     * @return list<TranslatableTypeInfo>
     */
    public function translatableTypes(): array
    {
        $types = [];

        foreach ($this->mappingManager->translatableTypes() as $name => $class) {
            $types[] = new TranslatableTypeInfo(
                $name,
                $class,
                $this->mappingManager->translatableFieldsFor($class)
            );
        }

        return $types;
    }

    /**
     * @param object|class-string $objectOrClass
     *
     * @return list<string>
     */
    public function translatableFieldsFor(object|string $objectOrClass): array
    {
        return $this->mappingManager->translatableFieldsFor($objectOrClass);
    }

    public function findTranslation(object $entity, string $locale, string $field): ?string
    {
        $translations = $this->findTranslations($entity, $locale);

        return $translations[$field] ?? null;
    }

    /**
     * @return array<string, string> field => translated value
     */
    public function findTranslations(object $entity, string $locale): array
    {
        $type = $this->mappingManager->translatableTypeFor($entity);
        $id = $this->mappingManager->idFor($entity);

        return $this->mappingManager->translationsFor($locale, $type, $id);
    }

    public function saveTranslation(object $entity, string $locale, string $field, string $value): void
    {
        $this->saveTranslations($entity, $locale, [$field => $value]);
    }

    /**
     * @param array<string, string> $fields field => value
     */
    public function saveTranslations(object $entity, string $locale, array $fields): void
    {
        $type = $this->mappingManager->translatableTypeFor($entity);
        $id = $this->mappingManager->idFor($entity);
        $unknownFields = array_diff(array_keys($fields), $this->mappingManager->translatableFieldsFor($entity));

        if ($unknownFields) {
            throw new \InvalidArgumentException(sprintf('Fields "%s" of "%s" are not translatable.', implode('", "', $unknownFields), $type));
        }

        foreach ($fields as $field => $value) {
            $this->mappingManager->upsert($type, $id, $locale, $field, $value);
        }

        $this->mappingManager->flush();
        $this->cache->invalidateObject($type, $id, $locale);
    }

    public function deleteTranslation(object $entity, string $locale, string $field): void
    {
        $this->delete($entity, $locale, $field);
    }

    public function deleteTranslations(object $entity, string $locale): void
    {
        $this->delete($entity, $locale);
    }

    public function translationStatusFor(object $entity, string $locale): TranslationStatus
    {
        $type = $this->mappingManager->translatableTypeFor($entity);
        $id = $this->mappingManager->idFor($entity);
        $allFields = $this->mappingManager->translatableFieldsFor($entity);
        $translations = $this->findTranslations($entity, $locale);

        $translatedFields = [];
        $missingFields = [];

        foreach ($allFields as $field) {
            if (isset($translations[$field])) {
                $translatedFields[] = $field;
            } else {
                $missingFields[] = $field;
            }
        }

        return new TranslationStatus(
            $type,
            $id,
            $locale,
            $translatedFields,
            $missingFields
        );
    }

    public function invalidateCacheForEntity(object $entity, ?string $locale = null): void
    {
        $type = $this->mappingManager->translatableTypeFor($entity);
        $id = $this->mappingManager->idFor($entity);

        $this->cache->invalidateObject($type, $id, $locale);
    }

    /**
     * @param class-string $class
     */
    public function invalidateCacheForType(string $class): void
    {
        $type = $this->mappingManager->translatableTypeFor($class);

        $this->cache->invalidateType($type, $this->mappingManager->idsFor($class));
    }

    public function invalidateAllTranslationCache(): void
    {
        $this->cache->invalidateAll($this->idsByType());
    }

    /**
     * @return \Generator<string, iterable<string>>
     */
    private function idsByType(): \Generator
    {
        foreach ($this->mappingManager->translatableTypes() as $type => $class) {
            yield $type => $this->mappingManager->idsFor($class);
        }
    }

    private function delete(object $entity, string $locale, ?string $field = null): void
    {
        $type = $this->mappingManager->translatableTypeFor($entity);
        $id = $this->mappingManager->idFor($entity);

        $this->mappingManager->delete($type, $id, $locale, $field);
        $this->cache->invalidateObject($type, $id, $locale);
    }
}
