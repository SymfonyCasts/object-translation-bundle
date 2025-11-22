<?php

namespace SymfonyCasts\ObjectTranslationBundle;

/**
 * @template T of object
 *
 * @mixin T
 */
final class TranslatedObject
{
    /**
     * @param T                    $_inner
     * @param array<string,string> $_translations
     */
    public function __construct(
        private object $_inner,
        private array $_translations,
    ) {
    }

    public function __call(string $name, array $arguments): mixed
    {
        if ($translatedValue = $this->translatedValue($name)) {
            return $translatedValue;
        }

        $method = $name;

        if (!method_exists($this->_inner, $name)) {
            $method = 'get'.ucfirst($name);
        }

        return $this->_inner->$method(...$arguments);
    }

    public function __get(string $name): mixed
    {
        return $this->_translations[$name] ?? $this->_inner->$name;
    }

    public function __isset(string $name): bool
    {
        return isset($this->_inner->$name);
    }

    private function translatedValue(string $name): ?string
    {
        if (isset($this->_translations[$name])) {
            return $this->_translations[$name];
        }

        if (!str_starts_with($name, 'get')) {
            return null;
        }

        $property = lcfirst(substr($name, 3));

        return $this->_translations[$property] ?? null;
    }
}
