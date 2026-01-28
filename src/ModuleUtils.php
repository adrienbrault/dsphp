<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

use BackedEnum;
use Closure;
use ReflectionClass;
use ReflectionProperty;

use function is_object;

/**
 * @internal Utilities for discovering Predict instances and deep-cloning modules
 */
final class ModuleUtils
{
    /**
     * Find all Predict instances in a module via reflection.
     *
     * Returns an array mapping property paths (e.g. "generate.predict") to Predict instances.
     *
     * @return array<string, Predict<object>>
     */
    public static function findPredictInstances(object $module, string $prefix = ''): array
    {
        $instances = [];
        $ref = new ReflectionClass($module);

        foreach ($ref->getProperties() as $prop) {
            $prop->setAccessible(true);
            if (!$prop->isInitialized($module)) {
                continue;
            }

            $value = $prop->getValue($module);
            $path = '' !== $prefix ? $prefix.'.'.$prop->getName() : $prop->getName();

            if ($value instanceof Predict) {
                $instances[$path] = $value;
            } elseif ($value instanceof ChainOfThought) {
                // ChainOfThought contains a public Predict
                $instances[$path.'.predict'] = $value->predict;
            } elseif (is_object($value) && !$value instanceof BackedEnum && !$value instanceof LM && !$value instanceof Closure) {
                // Recurse into other objects (user modules)
                $nested = self::findPredictInstances($value, $path);
                foreach ($nested as $nestedPath => $nestedPredict) {
                    $instances[$nestedPath] = $nestedPredict;
                }
            }
        }

        return $instances;
    }

    /**
     * Deep-clone a module, cloning all Predict and ChainOfThought instances.
     *
     * @template T of object
     *
     * @param T $module
     *
     * @return T
     */
    public static function deepClone(object $module): object
    {
        /** @var T $clone */
        $clone = clone $module;
        $ref = new ReflectionClass($clone);

        foreach ($ref->getProperties() as $prop) {
            $prop->setAccessible(true);
            if (!$prop->isInitialized($clone)) {
                continue;
            }

            $value = $prop->getValue($clone);

            if ($value instanceof Predict || $value instanceof ChainOfThought) {
                self::setPropertyValue($clone, $prop, clone $value);
            } elseif (is_object($value) && !$value instanceof BackedEnum && !$value instanceof LM && !$value instanceof Closure) {
                self::setPropertyValue($clone, $prop, self::deepClone($value));
            }
        }

        return $clone;
    }

    /**
     * Set a property value, handling readonly properties.
     */
    private static function setPropertyValue(object $object, ReflectionProperty $prop, mixed $value): void
    {
        $prop->setAccessible(true);
        if ($prop->isReadOnly()) {
            // For readonly properties, use closure binding
            $setter = Closure::bind(static function (object $obj, string $name, mixed $val): void {
                $obj->{$name} = $val; // @phpstan-ignore property.dynamicName
            }, null, $object::class);
            if (null !== $setter) {
                $setter($object, $prop->getName(), $value);
            }
        } else {
            $prop->setValue($object, $value);
        }
    }
}
