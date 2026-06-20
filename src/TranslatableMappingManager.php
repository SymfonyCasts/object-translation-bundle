<?php

namespace SymfonyCasts\ObjectTranslationBundle;

use Doctrine\ORM\EntityManagerInterface;
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

    public function delete(string $type, string $id, string $locale, ?string $field = null): void
    {
        $om = $this->doctrine->getManagerForClass($this->translationClass);

        if (!$om instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('Object manager for class "%s" must be an instance of EntityManagerInterface', $this->translationClass));
        }

        $qb = $om->createQueryBuilder()
            ->delete($this->translationClass, 't')
            ->where('t.objectType = :type')
            ->andWhere('t.objectId = :id')
            ->andWhere('t.locale = :locale')
            ->setParameter('type', $type)
            ->setParameter('id', $id)
            ->setParameter('locale', $locale);

        if ($field) {
            $qb->andWhere('t.field = :field')
                ->setParameter('field', $field);
        }

        $qb->getQuery()->execute();
    }

    public function deleteForType(string $type, ?string $locale = null): void
    {
        $om = $this->doctrine->getManagerForClass($this->translationClass);

        if (!$om instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('Object manager for class "%s" must be an instance of EntityManagerInterface', $this->translationClass));
        }

        $qb = $om->createQueryBuilder()
            ->delete($this->translationClass, 't')
            ->where('t.objectType = :type')
            ->setParameter('type', $type);

        if ($locale) {
            $qb->andWhere('t.locale = :locale')
                ->setParameter('locale', $locale);
        }

        $qb->getQuery()->execute();
    }

    public function getTranslatableTypeMap(): array
    {
        $map = [];

        foreach ($this->doctrine->getManagers() as $om) {
            foreach ($om->getMetadataFactory()->getAllMetadata() as $metadata) {
                $class = $metadata->getName();
                $reflectionClass = new \ReflectionClass($class);
                $attribute = $reflectionClass->getAttributes(Translatable::class)[0] ?? null;

                if (!$attribute) {
                    continue;
                }

                $type = $attribute->newInstance()->name;
                $map[$type] = $class;
            }
        }

        return $map;
    }

    public function getTranslatableFieldsForClass(string $class): array
    {
        $reflectionClass = new \ReflectionClass($class);
        $fields = [];

        foreach ($reflectionClass->getProperties() as $property) {
            if ($property->getAttributes(TranslatableProperty::class)) {
                $fields[] = $property->getName();
            }
        }

        return $fields;
    }
}
