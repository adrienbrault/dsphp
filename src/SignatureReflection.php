<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

use BackedEnum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function enum_exists;
use function explode;
use function implode;
use function is_array;
use function is_subclass_of;
use function json_decode;
use function ltrim;
use function preg_match;
use function rtrim;
use function strtolower;
use function trim;

/**
 * @internal Utility for reflecting on signature classes
 */
final class SignatureReflection
{
    /**
     * Extract the task instruction from the class docblock.
     *
     * @param class-string $class
     */
    public static function getTaskInstruction(string $class): string
    {
        $ref = new ReflectionClass($class);
        $doc = $ref->getDocComment();
        if (false === $doc) {
            return '';
        }

        // Strip comment markers and extract the text
        $lines = explode("\n", $doc);
        $text = [];
        foreach ($lines as $line) {
            $line = trim($line);
            $line = ltrim($line, '/* ');
            $line = rtrim($line, '* /');
            $line = trim($line);
            if ('' !== $line) {
                $text[] = $line;
            }
        }

        return implode(' ', $text);
    }

    /**
     * Get all InputField properties from a signature class.
     *
     * @param class-string $class
     *
     * @return list<array{name: string, type: string, desc: string}>
     */
    public static function getInputFields(string $class): array
    {
        return self::getFieldsWithAttribute($class, InputField::class);
    }

    /**
     * Get all OutputField properties from a signature class.
     *
     * @param class-string $class
     *
     * @return list<array{name: string, type: string, desc: string}>
     */
    public static function getOutputFields(string $class): array
    {
        return self::getFieldsWithAttribute($class, OutputField::class);
    }

    /**
     * Extract input field values from a signature instance.
     *
     * @return array<string, mixed>
     */
    public static function getInputValues(object $instance): array
    {
        return self::getFieldValues($instance, InputField::class);
    }

    /**
     * Extract all field values (input + output) from a signature instance.
     *
     * @return array<string, mixed>
     */
    public static function getAllFieldValues(object $instance): array
    {
        $inputValues = self::getFieldValues($instance, InputField::class);
        $outputValues = self::getFieldValues($instance, OutputField::class);

        return array_merge($inputValues, $outputValues);
    }

    /**
     * Cast a string value to the declared type of a signature property.
     *
     * @param class-string $class
     */
    public static function castValue(string $value, string $class, string $property): mixed
    {
        $ref = new ReflectionProperty($class, $property);
        $type = $ref->getType();

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        return match (true) {
            'string' === $typeName => $value,
            'bool' === $typeName => 'true' === strtolower($value) || '1' === $value,
            'int' === $typeName => (int) $value,
            'float' === $typeName => (float) $value,
            'array' === $typeName => self::castArrayValue($value),
            enum_exists($typeName) && is_subclass_of($typeName, BackedEnum::class) => $typeName::from($value),
            default => $value,
        };
    }

    /**
     * @param class-string $class
     * @param class-string<InputField>|class-string<OutputField> $attributeClass
     *
     * @return list<array{name: string, type: string, desc: string}>
     */
    private static function getFieldsWithAttribute(string $class, string $attributeClass): array
    {
        $ref = new ReflectionClass($class);
        $fields = [];

        foreach ($ref->getProperties() as $prop) {
            $attrs = $prop->getAttributes($attributeClass);
            if (0 === count($attrs)) {
                continue;
            }

            /** @var InputField|OutputField $attr */
            $attr = $attrs[0]->newInstance();

            $fields[] = [
                'name' => $prop->getName(),
                'type' => self::resolveType($prop),
                'desc' => $attr->desc,
            ];
        }

        return $fields;
    }

    /**
     * @param class-string<InputField>|class-string<OutputField> $attributeClass
     *
     * @return array<string, mixed>
     */
    private static function getFieldValues(object $instance, string $attributeClass): array
    {
        $ref = new ReflectionClass($instance);
        $values = [];

        foreach ($ref->getProperties() as $prop) {
            $attrs = $prop->getAttributes($attributeClass);
            if (0 === count($attrs)) {
                continue;
            }
            $values[$prop->getName()] = $prop->getValue($instance);
        }

        return $values;
    }

    private static function resolveType(ReflectionProperty $prop): string
    {
        $type = $prop->getType();

        if (!$type instanceof ReflectionNamedType) {
            return 'string';
        }

        $typeName = $type->getName();

        // Check for backed enum
        if (enum_exists($typeName) && is_subclass_of($typeName, BackedEnum::class)) {
            /** @var class-string<BackedEnum> $typeName */
            $cases = $typeName::cases();
            $caseValues = array_map(
                static fn (BackedEnum $case): string => (string) $case->value,
                $cases,
            );

            return implode(', ', $caseValues);
        }

        // Check for array with PHPDoc type
        if ('array' === $typeName) {
            $doc = $prop->getDocComment();
            if (false !== $doc && 1 === preg_match('/@var\s+(list<[^>]+>|array<[^>]+>)/', $doc, $matches)) {
                return $matches[1];
            }

            return 'list<string>';
        }

        return $typeName;
    }

    /**
     * @return list<string>
     */
    private static function castArrayValue(string $value): array
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_map(static fn (mixed $v): string => (string) $v, $decoded)); // @phpstan-ignore cast.string
        }

        // Fallback: split by newlines
        return array_values(array_filter(
            array_map(\trim(...), explode("\n", $value)),
            static fn (string $line): bool => '' !== $line,
        ));
    }
}
