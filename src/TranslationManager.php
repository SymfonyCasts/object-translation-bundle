<?php

namespace SymfonyCasts\ObjectTranslationBundle;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use SymfonyCasts\ObjectTranslationBundle\Dto\TranslatableTypeInfo;
use SymfonyCasts\ObjectTranslationBundle\Dto\TranslationStatus;

final class TranslationManager implements TranslationManagerInterface
{
    public function __construct(
        private TranslatableMappingManager $mappingManager,
        private ManagerRegistry $doctrine,
        private string $translationClass,
        private ?CacheInterface $cache = null,
    ) {
    }

    public function getTranslatableTypes(): array
    {
        $map = $this->mappingManager->getTranslatableTypeMap();
        $types = [];

        foreach ($map as $name => $class) {
            $types[] = new TranslatableTypeInfo(
                $name,
                $class,
                $this->mappingManager->getTranslatableFieldsForClass($class)
            );
        }

        return $types;
    }

    public function getTranslatableFields(string $class): array
    {
        return $this->mappingManager->getTranslatableFieldsForClass($class);
    }

    public function getObjectsForType(string $class): iterable
    {
        return $this->doctrine->getRepository($class)->findAll();
    }

    public function findTranslation(object $entity, string $locale, string $field): ?string
    {
        $translations = $this->findTranslations($entity, $locale);

        return $translations[$field] ?? null;
    }

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

    public function saveTranslations(object $entity, string $locale, array $fields): void
    {
        $type = $this->mappingManager->translatableTypeFor($entity);
        $id = $this->mappingManager->idFor($entity);

        foreach ($fields as $field => $value) {
            $this->mappingManager->upsert($type, $id, $locale, $field, $value);
        }

        $this->invalidateCacheForEntity($entity);
    }

    public function deleteTranslation(object $entity, string $locale, ?string $field = null): void
    {
        $type = $this->mappingManager->translatableTypeFor($entity);
        $id = $this->mappingManager->idFor($entity);

        $this->mappingManager->delete($type, $id, $locale, $field);
        $this->invalidateCacheForEntity($entity);
    }

    public function deleteTranslationsForType(string $class, ?string $locale = null): void
    {
        $map = $this->mappingManager->getTranslatableTypeMap();
        $type = array_search($class, $map, true);

        if (!$type) {
            throw new \LogicException(sprintf('Class "%s" is not translatable.', $class));
        }

        $this->mappingManager->deleteForType($type, $locale);
        $this->invalidateCacheForType($class);
    }

    public function getTranslationStatus(object $entity, string $locale): TranslationStatus
    {
        $type = $this->mappingManager->translatableTypeFor($entity);
        $id = $this->mappingManager->idFor($entity);
        $allFields = $this->mappingManager->getTranslatableFieldsForClass($entity::class);
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

    public function invalidateCacheForEntity(object $entity): void
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return;
        }

        $type = $this->mappingManager->translatableTypeFor($entity);
        $id = $this->mappingManager->idFor($entity);

        $this->cache->invalidateTags(["object-translation-{$type}-{$id}"]);
    }

    public function invalidateCacheForType(string $class): void
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return;
        }

        $map = $this->mappingManager->getTranslatableTypeMap();
        $type = array_search($class, $map, true);

        if ($type) {
            $this->cache->invalidateTags(["object-translation-{$type}"]);
        }
    }

    public function invalidateAllTranslationCache(): void
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return;
        }

        $this->cache->invalidateTags(['object-translation']);
    }
}
