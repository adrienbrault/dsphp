<?php

declare(strict_types=1);

namespace DSPy;

/**
 * Pairs the LLM's chain-of-thought reasoning with the typed output.
 *
 * @template T of object
 *
 * @mixin T
 */
final class Reasoning
{
    /**
     * @param T $output
     */
    public function __construct(
        public readonly string $reasoning,
        public readonly object $output,
    ) {}

    public function __get(string $name): mixed
    {
        return $this->output->{$name}; // @phpstan-ignore property.dynamicName
    }

    public function __isset(string $name): bool
    {
        return isset($this->output->{$name}); // @phpstan-ignore property.dynamicName
    }
}
