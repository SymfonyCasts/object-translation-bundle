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
        return $this->mappingManager->translatableTypes();
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

    public function findTranslation(object $object, string $locale, string $field): ?string
    {
        $translations = $this->findTranslationsFor($object, $locale);

        return $translations[$field] ?? null;
    }

    /**
     * @return array<string, string> field => translated value
     */
    public function findTranslationsFor(object $object, string $locale): array
    {
        $type = $this->mappingManager->translatableTypeFor($object);
        $id = $this->mappingManager->idFor($object);

        return $this->mappingManager->translationsFor($locale, $type, $id);
    }

    public function saveTranslation(object $object, string $locale, string $field, string $value): void
    {
        $this->saveTranslationsFor($object, $locale, [$field => $value]);
    }

    /**
     * @param array<string, string> $fields field => value
     */
    public function saveTranslationsFor(object $object, string $locale, array $fields): void
    {
        $type = $this->mappingManager->translatableTypeFor($object);
        $id = $this->mappingManager->idFor($object);
        $unknownFields = array_diff(array_keys($fields), $this->mappingManager->translatableFieldsFor($object));

        if ($unknownFields) {
            throw new \InvalidArgumentException(sprintf('Fields "%s" of "%s" are not translatable.', implode('", "', $unknownFields), $type));
        }

        foreach ($fields as $field => $value) {
            $this->mappingManager->upsert($type, $id, $locale, $field, $value);
        }

        $this->mappingManager->flush();
        $this->cache->invalidateObject($type, $id, $locale);
    }

    public function deleteTranslation(object $object, string $locale, string $field): void
    {
        $this->delete($object, $locale, $field);
    }

    public function deleteTranslationsFor(object $object, ?string $locale = null): void
    {
        $this->delete($object, $locale);
    }

    public function translationStatusFor(object $object, string $locale): TranslationStatus
    {
        $type = $this->mappingManager->translatableTypeFor($object);
        $id = $this->mappingManager->idFor($object);
        $allFields = $this->mappingManager->translatableFieldsFor($object);
        $translations = $this->findTranslationsFor($object, $locale);

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

    public function invalidateCacheFor(object $object, ?string $locale = null): void
    {
        $type = $this->mappingManager->translatableTypeFor($object);
        $id = $this->mappingManager->idFor($object);

        $this->cache->invalidateObject($type, $id, $locale);
    }

    private function delete(object $object, ?string $locale, ?string $field = null): void
    {
        $type = $this->mappingManager->translatableTypeFor($object);
        $id = $this->mappingManager->idFor($object);

        $this->mappingManager->delete($type, $id, $locale, $field);
        $this->mappingManager->flush();
        $this->cache->invalidateObject($type, $id, $locale);
    }
}
