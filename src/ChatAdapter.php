<?php

declare(strict_types=1);

namespace DSPy;

use BackedEnum;

use function is_array;
use function is_bool;
use function json_encode;
use function preg_match;
use function preg_quote;
use function rtrim;
use function trim;

/**
 * Default adapter. Uses field markers [[ ## field_name ## ]] in prompts.
 */
final class ChatAdapter implements Adapter
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
        $system .= "---\n\nFollow the following format.\n\n";
        foreach ($inputFields as $field) {
            $system .= '[[ ## '.$field['name'].' ## ]]';
            if ('' !== $field['desc']) {
                $system .= ' '.$field['desc'];
            }
            $system .= "\n\n";
        }
        foreach ($outputFields as $field) {
            $system .= '[[ ## '.$field['name'].' ## ]]';
            if ('' !== $field['desc']) {
                $system .= ' '.$field['desc'];
            } else {
                $system .= ' '.$field['type'];
            }
            $system .= "\n\n";
        }
        $system = rtrim($system);

        $messages = [
            ['role' => 'system', 'content' => $system],
        ];

        // Format demos as user/assistant pairs
        foreach ($demos as $demo) {
            $userContent = '';
            foreach ($inputFields as $field) {
                if (isset($demo[$field['name']])) {
                    $userContent .= '[[ ## '.$field['name'].' ## ]]'."\n".$this->formatValue($demo[$field['name']])."\n\n";
                }
            }
            $messages[] = ['role' => 'user', 'content' => rtrim($userContent)];

            $assistantContent = '';
            foreach ($outputFields as $field) {
                if (isset($demo[$field['name']])) {
                    $assistantContent .= '[[ ## '.$field['name'].' ## ]]'."\n".$this->formatValue($demo[$field['name']])."\n\n";
                }
            }
            if (isset($demo['reasoning'])) {
                $assistantContent = '[[ ## reasoning ## ]]'."\n".(string) $demo['reasoning']."\n\n".$assistantContent; // @phpstan-ignore cast.string
            }
            $messages[] = ['role' => 'assistant', 'content' => rtrim($assistantContent)];
        }

        // Format current inputs
        $userContent = '';
        foreach ($inputFields as $field) {
            if (isset($inputs[$field['name']])) {
                $userContent .= '[[ ## '.$field['name'].' ## ]]'."\n".$this->formatValue($inputs[$field['name']])."\n\n";
            }
        }
        $messages[] = ['role' => 'user', 'content' => rtrim($userContent)];

        return $messages;
    }

    public function getOptions(string $signatureClass): array
    {
        return [];
    }

    public function parseResponse(string $signatureClass, string $response): array
    {
        $outputFields = SignatureReflection::getOutputFields($signatureClass);
        $values = [];

        // Parse [[ ## field_name ## ]] markers from response
        foreach ($outputFields as $field) {
            $pattern = '/\[\[\s*##\s*'.preg_quote($field['name'], '/').'\s*##\s*\]\]\s*\n?(.*?)(?=\[\[\s*##|$)/s';
            if (1 === preg_match($pattern, $response, $matches) && isset($matches[1])) {
                $rawValue = trim($matches[1]);
                $values[$field['name']] = SignatureReflection::castValue($rawValue, $signatureClass, $field['name']);
            }
        }

        return $values;
    }

    /**
     * Parse reasoning from a response that includes a reasoning section.
     */
    public function parseReasoning(string $response): ?string
    {
        $pattern = '/\[\[\s*##\s*reasoning\s*##\s*\]\]\s*\n?(.*?)(?=\[\[\s*##|$)/s';
        if (1 === preg_match($pattern, $response, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value; // @phpstan-ignore cast.string
    }
}
