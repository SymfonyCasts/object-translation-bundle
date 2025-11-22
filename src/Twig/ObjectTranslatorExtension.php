<?php

namespace SymfonyCasts\ObjectTranslationBundle\Twig;

use SymfonyCasts\ObjectTranslationBundle\ObjectTranslator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @internal
 */
final class ObjectTranslatorExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('translate_object', [ObjectTranslator::class, 'translate']),
        ];
    }
}
