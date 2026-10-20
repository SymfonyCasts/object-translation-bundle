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

If you want to build a custom administration interface for managing translations in your
application (e.g. using Symfony UX, React or plain PHP), the bundle provides a
`TranslationManager` service. It allows you to access translations programmatically without
needing to interact directly with the database or internal services.

### `TranslationManager` Service

You can inject `TranslationManager` to perform CRUD operations, retrieve translation status,
or invalidate the cache.

```php
use SymfonyCasts\ObjectTranslationBundle\TranslationManager;

class TranslationAdminController
{
    public function edit(Product $product, TranslationManager $translationManager)
    {
        // Save a translation for a specific field and locale
        $translationManager->saveTranslation($product, 'sk', 'name', 'Názov produktu');

        // Save multiple translations at once (a null value deletes that field's translation)
        $translationManager->saveTranslationsFor($product, 'sk', [
            'name' => 'Názov produktu',
            'description' => 'Dlhý popis produktu...',
        ]);

        // Get all saved translations for a given entity and locale: ['name' => '...', ...]
        $translations = $translationManager->findTranslationsFor($product, 'sk');

        // Get translation status (which fields are filled and which are missing)
        $status = $translationManager->translationStatusFor($product, 'sk');
        $status->completionRatio(); // e.g. 0.85
        $status->missingFields; // list of fields that are not yet translated

        // Manually invalidate cache for this entity
        $translationManager->invalidateCacheFor($product);
    }
}
```

For automatic discovery of translatable entities, you can use:

```php
// Get list of all classes marked with #[Translatable] attribute: list<TranslatableTypeInfo>
$types = $translationManager->translatableTypes();

// Get fields for a specific class or object
$fields = $translationManager->translatableFieldsFor(Product::class);
```

### `ObjectTranslationType` Form Type

If `symfony/form` is installed, you can use `ObjectTranslationType` to build an admin
form for translating an object into a locale. It has a field for each translatable property,
prefilled with any existing translation and showing the original value as help text. Passing
the submitted data to `saveTranslationsFor()` creates, updates or deletes (for fields left
empty) the translations:

```php
use SymfonyCasts\ObjectTranslationBundle\Form\ObjectTranslationType;

public function translate(Product $product, string $locale, Request $request, TranslationManager $translationManager): Response
{
    $form = $this->createForm(ObjectTranslationType::class, options: [
        'object' => $product,
        'locale' => $locale,
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $translationManager->saveTranslationsFor($product, $locale, $form->getData());

        // redirect...
    }

    // render...
}
```

Text columns render as a textarea, other columns as a text input.

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

If your cache pool supports *cache tagging*, tags are added to the cache keys. Three tags
are added:

- `object-translation`: All translations are tagged with this key.
- `object-translation-{type}`: Where `{type}` is the translatable alias (e.g., `product`).
- `object-translation-{type}-{id}`: Where `{id}` is the object's identifier.

You can invalidate these tags by using the `cache:pool:invalidate-tags` command:

```bash
# invalidate all object translation caches
symfony console cache:pool:invalidate-tags object-translation

# invalidate only the translation cache for "product" entities
symfony console cache:pool:invalidate-tags object-translation-product

# invalidate only the translation cache for product 42
symfony console cache:pool:invalidate-tags object-translation-product-42
```

Translations saved or deleted with `TranslationManager` or the `object-translation:import`
command are removed from the cache automatically, whether or not your pool supports tags.
Without tags, `TranslationManager::invalidateCacheFor()` called without a locale
clears the cache for each of your app's enabled locales.

### `object-translation:warmup` Command

This command preloads all translations into the cache for all your
app's enabled locales.

```bash
symfony console object-translation:warmup
```

## Cookbook

### Translating Entities in EasyAdmin

Add a "Translate" action to your [EasyAdmin](https://github.com/EasyCorp/EasyAdminBundle)
CRUD controller that renders an `ObjectTranslationType` form for each of your app's
enabled locales, except the default one:

```php
// src/Controller/Admin/ProductCrudController.php

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use SymfonyCasts\ObjectTranslationBundle\Form\ObjectTranslationType;
use SymfonyCasts\ObjectTranslationBundle\TranslationManager;

class ProductCrudController extends AbstractCrudController
{
    // ...

    public function configureActions(Actions $actions): Actions
    {
        $translate = Action::new('translate', 'Translate', 'fa fa-language')
            ->linkToCrudAction('translate');

        return $actions
            ->add(Crud::PAGE_INDEX, $translate)
            ->add(Crud::PAGE_DETAIL, $translate)
            ->add(Crud::PAGE_EDIT, $translate);
    }

    #[AdminRoute('/{entityId}/translate/{locale?}')]
    public function translate(
        AdminContext $context,
        Request $request,
        TranslationManager $translationManager,

        #[Autowire('%kernel.enabled_locales%')]
        array $enabledLocales,

        #[Autowire('%kernel.default_locale%')]
        string $defaultLocale,
        ?string $locale = null,
    ): Response {
        // the default locale holds the original values, so it is not translated
        $locales = array_values(array_diff($enabledLocales, [$defaultLocale]));
        $product = $context->getEntity()->getInstance();
        $locale ??= $locales[0];

        if (!in_array($locale, $locales, true)) {
            throw $this->createNotFoundException(sprintf('Locale "%s" is not enabled.', $locale));
        }

        $form = $this->createForm(ObjectTranslationType::class, options: [
            'object' => $product,
            'locale' => $locale,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $translationManager->saveTranslationsFor($product, $locale, $form->getData());
            $this->addFlash('success', sprintf('"%s" translations saved.', $locale));

            return $this->redirect($request->getUri());
        }

        return $this->render('admin/translate.html.twig', [
            'form' => $form,
            'locale' => $locale,
            'locales' => $locales,
            'status' => $translationManager->translationStatusFor($product, $locale),
        ]);
    }
}
```

The template extends EasyAdmin's layout, uses its form theme, links back to the edit page
and links to the same action for each locale:

```twig
{# templates/admin/translate.html.twig #}

{% extends '@EasyAdmin/page/content.html.twig' %}
{% form_theme form with ['@EasyAdmin/crud/form_theme.html.twig'] only %}

{% block content_title %}Translate "{{ ea.entity }}"{% endblock %}

{% block page_actions %}
    <a class="btn btn-secondary" href="{{ ea_url().setAction('edit').unset('locale') }}">Back to {{ ea.entity }}</a>
{% endblock %}

{% block main %}
    <ul class="nav nav-tabs mb-3">
        {% for l in locales %}
            <li class="nav-item">
                <a class="nav-link {{ l == locale ? 'active' }}" href="{{ ea_url().set('locale', l) }}">{{ l }}</a>
            </li>
        {% endfor %}
    </ul>

    <p>{{ status.translatedFields|length }} of {{ (status.translatedFields|length) + (status.missingFields|length) }} fields translated</p>

    {{ form_start(form) }}
        {{ form_widget(form) }}
        <button type="submit" class="btn btn-primary">Save</button>
    {{ form_end(form) }}
{% endblock %}
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

## Security Policy

If you discover a security vulnerability, please do not open a public issue or pull request. Instead, please review this repository's [Security Policy](https://github.com/SymfonyCasts/object-translation-bundle/security) for instructions on how to report it responsibly.
