<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

use function array_key_exists;
use function array_map;
use function explode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function rtrim;
use function str_contains;

/**
 * Uses the model's native JSON mode for structured output.
 */
final class JsonAdapter implements Adapter
{
    public function formatMessages(string $signatureClass, array $demos, array $inputs): array
    {
        $instruction = SignatureReflection::getTaskInstruction($signatureClass);
        $inputFields = SignatureReflection::getInputFields($signatureClass);
        $outputFields = SignatureReflection::getOutputFields($signatureClass);

        // Build system message
        $system = '';
        if ('' !== $instruction) {
            $system .= $instruction."\n\n";
        }
        $system .= "Respond with a JSON object containing the following fields:\n\n";
        foreach ($outputFields as $field) {
            $system .= '- "'.$field['name'].'" ('.$field['type'].')';
            if ('' !== $field['desc']) {
                $system .= ': '.$field['desc'];
            }
            $system .= "\n";
        }

        $messages = [
            ['role' => 'system', 'content' => rtrim($system)],
        ];

        // Format demos
        foreach ($demos as $demo) {
            $userParts = [];
            foreach ($inputFields as $field) {
                if (isset($demo[$field['name']])) {
                    $userParts[$field['name']] = $demo[$field['name']];
                }
            }
            $messages[] = ['role' => 'user', 'content' => json_encode($userParts, JSON_THROW_ON_ERROR)];

            $assistantParts = [];
            foreach ($outputFields as $field) {
                if (isset($demo[$field['name']])) {
                    $assistantParts[$field['name']] = $demo[$field['name']];
                }
            }
            $messages[] = ['role' => 'assistant', 'content' => json_encode($assistantParts, JSON_THROW_ON_ERROR)];
        }

        // Format current inputs
        $userParts = [];
        foreach ($inputFields as $field) {
            if (isset($inputs[$field['name']])) {
                $userParts[$field['name']] = $inputs[$field['name']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => json_encode($userParts, JSON_THROW_ON_ERROR)];

        return $messages;
    }

    public function getOptions(string $signatureClass): array
    {
        $outputFields = SignatureReflection::getOutputFields($signatureClass);

        $properties = [];
        $required = [];
        foreach ($outputFields as $field) {
            $required[] = $field['name'];
            $properties[$field['name']] = self::fieldTypeToJsonSchema($field);
        }

        return [
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'output',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => $required,
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];
    }

    public function parseResponse(string $signatureClass, string $response): array
    {
        $outputFields = SignatureReflection::getOutputFields($signatureClass);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        $values = [];
        foreach ($outputFields as $field) {
            if (array_key_exists($field['name'], $decoded)) {
                $raw = $decoded[$field['name']];
                if (is_string($raw)) {
                    $values[$field['name']] = SignatureReflection::castValue($raw, $signatureClass, $field['name']);
                } else {
                    $values[$field['name']] = $raw;
                }
            }
        }

        return $values;
    }

    public function parseReasoning(string $response): ?string
    {
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['reasoning']) && is_string($decoded['reasoning'])) {
            return $decoded['reasoning'];
        }

        return null;
    }

    /**
     * @param array{name: string, type: string, desc: string} $field
     *
     * @return array<string, mixed>
     */
    private static function fieldTypeToJsonSchema(array $field): array
    {
        $type = $field['type'];

        if ('string' === $type) {
            return ['type' => 'string'];
        }
        if ('bool' === $type) {
            return ['type' => 'boolean'];
        }
        if ('int' === $type) {
            return ['type' => 'integer'];
        }
        if ('float' === $type) {
            return ['type' => 'number'];
        }
        if ('list<string>' === $type) {
            return ['type' => 'array', 'items' => ['type' => 'string']];
        }

        // Enum-like: "positive, negative, neutral"
        if (str_contains($type, ',')) {
            $enumValues = array_map(\trim(...), explode(',', $type));

            return ['type' => 'string', 'enum' => $enumValues];
        }

        return ['type' => 'string'];
    }
}
