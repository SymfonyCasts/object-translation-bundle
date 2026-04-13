# symfonycasts/object-translation-bundle

This bundle provides a simple way to translate Doctrine entities in Symfony applications.

## Installation

### Install the bundle via Composer:

```bash
composer require symfonycasts/object-translation-bundle
```

### Enable the bundle in your `config/bundles.php` file:

> [!NOTE]
> This step is not required if you are using Symfony Flex.

```php
return [
    // ...
    ObjectTranslationBundle::class => ['all' => true],
];
```

### Create the translation entity in your app:

> [!NOTE]
> This step is not required if you are using Symfony Flex.

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use SymfonyCasts\ObjectTranslationBundle\Model\Translation as BaseTranslation;

#[ORM\Entity]
class Translation extends BaseTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;
}
```

### Configure the entity in your `config/packages/object_translation.yaml` file:

> [!NOTE]
> This step is not required if you are using Symfony Flex.

```yaml
symfonycasts_object_translation:
    translation_class: App\Entity\Translation
```

### Create and run the migration to add the translation table:

```bash
symfony console make:migration
symfony console doctrine:migrations:migrate
```

## Marking Entities as Translatable

To mark an entity as translatable, use the `Translatable` attribute on the entity class
and the `TranslatableProperty` attribute on the fields you want to translate.

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use SymfonyCasts\ObjectTranslationBundle\Mapping\Translatable;
use SymfonyCasts\ObjectTranslationBundle\Mapping\TranslatableProperty;

#[ORM\Entity]
#[Translatable('product')]
class Product
{
    // ...

    #[ORM\Column(type: 'string', length: 255)]
    #[TranslatableProperty]
    public string $name;

    #[ORM\Column(type: 'text')]
    #[TranslatableProperty]
    public string $description;
}
```

## Usage

### `ObjectTranslator` Service

You can inject the `ObjectTranslator` service to translate entities.

```php
use SymfonyCasts\ObjectTranslationBundle\ObjectTranslator;

class ProductController
{
    public function show(Product $product, ObjectTranslator $objectTranslator)
    {
        // translate into the current request locale
        $translatedProduct = $objectTranslator->translate($product);

        $translatedProduct->getName(); // returns the translated name (if available)
        $translatedProduct->getDescription(); // returns the translated description (if available)

        // ...
    }
}
```

The second argument of the `translate()` method allows you to specify a locale:

```php
$product = $objectTranslator->translate($product, 'fr'); // translates into French
```

### `translate_object` Twig Filter

If using Twig, you can use the `translate_object` filter to translate entities directly in templates.

```twig
{% set translatedProduct = product|translate_object %} {# translates into the current request locale #}

{% set frenchProduct = product|translate_object('fr') %} {# translates into French #}
```

## Managing Translations

The `Translation` database table has the following structure:

- `id`: Primary key (added by you)
- `object_type`: The *alias* defined in the `Translatable` attribute (e.g., `product`)
- `object_id`: The ID of the translated entity
- `locale`: The locale of the translation (e.g., `fr`)
- `field`: The entity property name being translated (e.g., `description`)
- `value`: The translated value

Each row represents a single property translation for a specific entity in a specific locale.

You can manage these translations yourself but two console commands are provided to help:

### `object-translation:export`

This command exports all entity translations, in your default locale, to a CSV file.

```bash
symfony console object-translation:export translations.csv
```

This will create a `translations.csv` file at the root of your project with the following structure:

```csv
type,id,field,value
```

You can then take this file to translation service for translation. Be sure to keep
the `type`, `id`, and `field` columns intact. The `value` column is what needs to be translated
into the desired language.

### `object-translation:import`

This command imports translations from a CSV file created by the `export` command after
the `value` column has been translated.

```bash
symfony console object-translation:import translations_fr.csv fr
```

The first argument is the path to the CSV file, and the second argument is the locale
of the translations in that file.

## Translation Management API

If you want to build a custom administration interface for managing translations in your application (e.g. using Symfony UX, React or plain PHP), the bundle provides a stable `TranslationManagerInterface`. This interface allows you to access translations programmatically without needing to interact directly with the database or internal services.

### `TranslationManager` Service

You can inject `TranslationManagerInterface` (an alias for `TranslationManager`) to perform CRUD operations, retrieve translation status, or invalidate the cache.

```php
use SymfonyCasts\ObjectTranslationBundle\TranslationManagerInterface;

class TranslationAdminController
{
    public function edit(Product $product, TranslationManagerInterface $translationManager)
    {
        // Save a translation for a specific field and locale
        $translationManager->saveTranslation($product, 'sk', 'name', 'Názov produktu');
        
        // Save multiple translations at once
        $translationManager->saveTranslations($product, 'sk', [
            'name' => 'Názov produktu',
            'description' => 'Dlhý popis produktu...',
        ]);

        // Get all saved translations for a given entity and locale
        $translations = $translationManager->findTranslations($product, 'sk'); // returns ['name' => '...', ...]

        // Get translation status (which fields are filled and which are missing)
        $status = $translationManager->getTranslationStatus($product, 'sk');
        $status->getCompletionPercentage(); // e.g. 0.85 (85%)
        $status->missingFields; // list of fields that are not yet translated

        // Manually invalidate cache for this entity
        $translationManager->invalidateCacheForEntity($product);
    }
}
```

For automatic discovery of translatable entities, you can use:

```php
// Get list of all classes marked with #[Translatable] attribute
$types = $translationManager->getTranslatableTypes(); // returns list<TranslatableTypeInfo>

// Get fields for a specific class
$fields = $translationManager->getTranslatableFields(Product::class);

// Get all objects of a given class (useful for lists in administration)
$objects = $translationManager->getObjectsForType(Product::class);
```

## Translation Caching

For performance, translations are cached. By default, they use your `cache.app` pool
and have no expiration time. This can be configured:

```yaml
symfonycasts_object_translation:
    cache:
        pool: 'cache.object_translation' # a custom pool name
        ttl: 3600 # expire after one hour
```

### Translation Tags

If your cache pool supports *cache tagging*, tags are added to the cache keys. Two keys
are added:

- `object-translation`: All translations are tagged with this key.
- `object-translation-{type}`: Where `{type}` is the translatable alias (e.g., `product`).

You can invalidate these tags by using the `cache:pool:invalidate-tags` command:

```bash
# invalidate all object translation caches
symfony console cache:pool:invalidate-tags object-translation

# invalidate only the translation cache for "product" entities
symfony console cache:pool:invalidate-tags object-translation-product
```

### `object-translation:warmup` Command

This command preloads all translations into the cache for all your
app's enabled locales.

```bash
symfony console object-translation:warmup
```

## Full Default Configuration

```yaml
symfonycasts_object_translation:

    # The class name of your translation entity.
    translation_class:    ~ # Required, Example: App\Entity\Translation

    # Cache settings for object translations.
    cache:
        enabled:              true

        # The cache pool to use for storing object translations.
        pool:                 cache.app

        # The time-to-livefor cached translations, in seconds, null for no expiration.
        ttl:                  null
```
