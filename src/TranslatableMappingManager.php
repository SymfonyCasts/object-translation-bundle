<?php

namespace SymfonyCasts\ObjectTranslationBundle;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Proxy;
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

    public function translatableTypeFor(object $object): string
    {
        $class = new \ReflectionClass($object);

        if ($class->implementsInterface(Proxy::class)) {
            $class = $class->getParentClass();
        }

        $type = ($class->getAttributes(Translatable::class)[0] ?? null)?->newInstance()->name ?? null;

        if (!$type) {
            throw new \LogicException(sprintf('Class "%s" is not translatable.', $object::class));
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

    public function allTranslatableObjects(): iterable
    {
        foreach ($this->doctrine->getManagers() as $om) {
            foreach ($om->getMetadataFactory()->getAllMetadata() as $metadata) {
                $class = $metadata->getName();

                if (!(new \ReflectionClass($class))->getAttributes(Translatable::class)) {
                    continue;
                }

                yield from $this->doctrine->getRepository($class)->findAll();
            }
        }
    }

    public function translatableValuesFor(object $object): iterable
    {
        $class = new \ReflectionClass($object);

        foreach ($class->getProperties() as $property) {
            if (!$property->getAttributes(TranslatableProperty::class)) {
                continue;
            }

            yield $property->getName() => $property->getValue($object);
        }
    }

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
        $om->flush();
    }
}
