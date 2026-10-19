<?php

namespace SymfonyCasts\ObjectTranslationBundle;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Proxy;
use SymfonyCasts\ObjectTranslationBundle\Dto\TranslatableTypeInfo;
use SymfonyCasts\ObjectTranslationBundle\Mapping\Translatable;
use SymfonyCasts\ObjectTranslationBundle\Mapping\TranslatableProperty;
use SymfonyCasts\ObjectTranslationBundle\Model\Translation;

/**
 * @internal
 */
final class TranslatableMappingManager
{
    public function __construct(
        private string $translationClass,
        private ManagerRegistry $doctrine,
    ) {
    }

    public function translatableTypeFor(object|string $objectOrClass): string
    {
        $class = self::reflectionFor($objectOrClass);
        $type = ($class->getAttributes(Translatable::class)[0] ?? null)?->newInstance()->name ?? null;

        if (!$type) {
            throw new \LogicException(sprintf('Class "%s" is not translatable.', $class->name));
        }

        return $type;
    }

    public function idFor(object $object): string
    {
        $om = $this->doctrine->getManagerForClass($object::class);

        if (!$om) {
            throw new \LogicException(sprintf('No object manager found for class "%s"', $object::class));
        }

        $id = $om->getClassMetadata($object::class)->getIdentifierValues($object);

        if (count($id) > 1) {
            throw new \LogicException(sprintf('Class "%s" must have a single identifier to be translatable.', $object::class));
        }

        return reset($id);
    }

    public function translationsFor(string $locale, string $type, string $id): array
    {
        /** @var Translation[] $translations */
        $translations = $this->doctrine->getRepository($this->translationClass)
            ->findBy([
                'locale' => $locale,
                'objectType' => $type,
                'objectId' => $id,
            ]);

        $translationValues = [];

        foreach ($translations as $translation) {
            $translationValues[$translation->field] = $translation->value;
        }

        return $translationValues;
    }

    /**
     * @return iterable<TranslatableTypeInfo, object>
     */
    public function allTranslatableObjects(): iterable
    {
        foreach ($this->translatableTypes() as $type) {
            foreach ($this->doctrine->getRepository($type->class)->findAll() as $object) {
                yield $type => $object;
            }
        }
    }

    /**
     * @return list<TranslatableTypeInfo>
     */
    public function translatableTypes(): array
    {
        $types = [];

        foreach ($this->translatableClasses() as $class) {
            $types[] = new TranslatableTypeInfo(
                $this->translatableTypeFor($class),
                $class,
                $this->translatableFieldsFor($class),
            );
        }

        return $types;
    }

    /**
     * @return list<string>
     */
    public function translatableFieldsFor(object|string $objectOrClass): array
    {
        $fields = [];

        foreach (self::translatablePropertiesFor($objectOrClass) as $property) {
            $fields[] = $property->getName();
        }

        return $fields;
    }

    public function translatableValuesFor(object $object): iterable
    {
        foreach (self::translatablePropertiesFor($object) as $property) {
            yield $property->getName() => $property->getValue($object);
        }
    }

    /**
     * Only persists the translation, call flush() to write it.
     */
    public function upsert(string $type, string $id, string $locale, string $field, string $value): void
    {
        $om = $this->doctrine->getManagerForClass($this->translationClass);
        $translation = $om->getRepository($this->translationClass)->findOneBy([
            'objectType' => $type,
            'objectId' => $id,
            'locale' => $locale,
            'field' => $field,
        ]);

        if (!$translation) {
            $translation = new ($this->translationClass)();
            $translation->objectType = $type;
            $translation->objectId = $id;
            $translation->locale = $locale;
            $translation->field = $field;
        }

        $translation->value = $value;

        $om->persist($translation);
    }

    public function flush(): void
    {
        $this->doctrine->getManagerForClass($this->translationClass)->flush();
    }

    /**
     * Only removes the translations, call flush() to write the removal.
     */
    public function delete(string $type, string $id, ?string $locale = null, ?string $field = null): void
    {
        $om = $this->doctrine->getManagerForClass($this->translationClass);
        $criteria = array_filter(
            ['objectType' => $type, 'objectId' => $id, 'locale' => $locale, 'field' => $field],
            static fn (?string $value) => null !== $value,
        );

        foreach ($om->getRepository($this->translationClass)->findBy($criteria) as $translation) {
            $om->remove($translation);
        }
    }

    /**
     * @return iterable<class-string>
     */
    private function translatableClasses(): iterable
    {
        foreach ($this->doctrine->getManagers() as $om) {
            foreach ($om->getMetadataFactory()->getAllMetadata() as $metadata) {
                if ($metadata->getReflectionClass()->getAttributes(Translatable::class)) {
                    yield $metadata->getName();
                }
            }
        }
    }

    /**
     * @return iterable<\ReflectionProperty>
     */
    private static function translatablePropertiesFor(object|string $objectOrClass): iterable
    {
        foreach (self::reflectionFor($objectOrClass)->getProperties() as $property) {
            if ($property->getAttributes(TranslatableProperty::class)) {
                yield $property;
            }
        }
    }

    /**
     * Doctrine proxies hide the real class's attributes and private properties.
     */
    private static function reflectionFor(object|string $objectOrClass): \ReflectionClass
    {
        $class = new \ReflectionClass($objectOrClass);

        if ($class->implementsInterface(Proxy::class)) {
            return $class->getParentClass();
        }

        return $class;
    }
}
