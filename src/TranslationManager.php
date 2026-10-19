<?php

namespace SymfonyCasts\ObjectTranslationBundle;

use Doctrine\Persistence\ManagerRegistry;
use SymfonyCasts\ObjectTranslationBundle\Dto\TranslatableTypeInfo;
use SymfonyCasts\ObjectTranslationBundle\Dto\TranslationStatus;

final class TranslationManager
{
    public function __construct(
        private TranslatableMappingManager $mappingManager,
        private ManagerRegistry $doctrine,
        private TranslationCache $cache,
    ) {
    }

    /** @return list<TranslatableTypeInfo> */
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

    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    public function getTranslatableFields(string $class): array
    {
        return $this->mappingManager->getTranslatableFieldsForClass($class);
    }

    /**
     * @param class-string $class
     *
     * @return iterable<object>
     */
    public function getObjectsForType(string $class): iterable
    {
        $manager = $this->doctrine->getManagerForClass($class);

        if (!$manager instanceof \Doctrine\ORM\EntityManagerInterface) {
            throw new \LogicException(sprintf('Manager for class "%s" must be an instance of EntityManagerInterface.', $class));
        }

        return $manager->createQueryBuilder()
            ->select('o')
            ->from($class, 'o')
            ->getQuery()
            ->toIterable();
    }

    public function findTranslation(object $entity, string $locale, string $field): ?string
    {
        $translations = $this->findTranslations($entity, $locale);

        return $translations[$field] ?? null;
    }

    /** @return array<string, string> field => translated value */
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

    /** @param array<string, string> $fields field => value */
    public function saveTranslations(object $entity, string $locale, array $fields): void
    {
        $type = $this->mappingManager->translatableTypeFor($entity);
        $id = $this->mappingManager->idFor($entity);

        foreach ($fields as $field => $value) {
            $this->mappingManager->upsert($type, $id, $locale, $field, $value);
        }

        $this->cache->invalidateObject($type, $id, $locale);
    }

    /** Deletes every field when $field is null. */
    public function deleteTranslation(object $entity, string $locale, ?string $field = null): void
    {
        $type = $this->mappingManager->translatableTypeFor($entity);
        $id = $this->mappingManager->idFor($entity);

        $this->mappingManager->delete($type, $id, $locale, $field);
        $this->cache->invalidateObject($type, $id, $locale);
    }

    /** @param class-string $class */
    public function deleteTranslationsForType(string $class, ?string $locale = null): void
    {
        $map = $this->mappingManager->getTranslatableTypeMap();
        $type = array_search($class, $map, true);

        if (!$type) {
            throw new \LogicException(sprintf('Class "%s" is not translatable.', $class));
        }

        $this->mappingManager->deleteForType($type, $locale);
        $this->cache->invalidateType($type, $this->mappingManager->idsFor($class), $locale);
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

    public function invalidateCacheForEntity(object $entity, ?string $locale = null): void
    {
        $type = $this->mappingManager->translatableTypeFor($entity);
        $id = $this->mappingManager->idFor($entity);

        $this->cache->invalidateObject($type, $id, $locale);
    }

    /** @param class-string $class */
    public function invalidateCacheForType(string $class): void
    {
        $map = $this->mappingManager->getTranslatableTypeMap();
        $type = array_search($class, $map, true);

        if ($type) {
            $this->cache->invalidateType($type, $this->mappingManager->idsFor($class));
        }
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
        foreach ($this->mappingManager->getTranslatableTypeMap() as $type => $class) {
            yield $type => $this->mappingManager->idsFor($class);
        }
    }
}
