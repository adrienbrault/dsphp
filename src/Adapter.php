<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

interface Adapter
{
    /**
     * Build the message list for the LLM from a signature, demos, and inputs.
     *
     * @param class-string $signatureClass
     * @param list<array<string, mixed>> $demos Few-shot demo field values
     * @param array<string, mixed> $inputs Current input field values
     *
     * @return list<array{role: 'assistant'|'system'|'user', content: string}>
     */
    public function formatMessages(string $signatureClass, array $demos, array $inputs): array;

    /**
     * Return adapter-specific LM options (e.g. response_format for JSON mode).
     *
     * @param class-string $signatureClass
     *
     * @return array<string, mixed>
     */
    public function getOptions(string $signatureClass): array;

    /**
     * Parse the LLM response text into output field values.
     *
     * @param class-string $signatureClass
     *
     * @return array<string, mixed>
     */
    public function parseResponse(string $signatureClass, string $response): array;

    /**
     * Extract chain-of-thought reasoning from the LLM response, if present.
     */
    public function parseReasoning(string $response): ?string;
}
