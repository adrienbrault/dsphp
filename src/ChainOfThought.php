<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

use RuntimeException;
use Throwable;

use function array_map;
use function count;
use function implode;

/**
 * @template T of object
 */
final class ChainOfThought
{
    /** @var Predict<T> */
    public readonly Predict $predict;

    /**
     * @param class-string<T> $signature
     */
    public function __construct(
        private readonly string $signature,
        private readonly LM $lm,
        private readonly Adapter $adapter = new ChatAdapter(),
        private readonly int $maxRetries = 3,
    ) {
        $this->predict = new Predict($signature, $lm, $adapter, $maxRetries);
    }

    public function __clone()
    {
        $this->predict = clone $this->predict;
    }

    /**
     * @param T $input
     *
     * @return Reasoning<T>
     */
    public function __invoke(object $input): Reasoning
    {
        $inputValues = SignatureReflection::getInputValues($input);
        $outputFields = SignatureReflection::getOutputFields($this->signature);

        // Build messages with reasoning instruction
        $messages = $this->adapter->formatMessages($this->signature, $this->predict->demos, $inputValues);

        // Modify system message to include reasoning instruction
        if (count($messages) > 0 && 'system' === $messages[0]['role']) {
            $outputFieldNames = array_map(
                static fn (array $f): string => $f['name'],
                $outputFields,
            );
            $messages[0]['content'] .= "\n\n[[ ## reasoning ## ]] Think step by step to work towards the ".implode(', ', $outputFieldNames).'.';
        }

        $options = $this->adapter->getOptions($this->signature);
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; ++$attempt) {
            $response = $this->lm->chat($messages, $options);

            try {
                $outputValues = $this->adapter->parseResponse($this->signature, $response);

                if (0 === count($outputValues)) {
                    throw new RuntimeException('No output fields parsed from response');
                }

                $reasoning = $this->adapter->parseReasoning($response) ?? '';

                /** @var T $output */
                $output = new ($this->signature)(...$inputValues, ...$outputValues);

                return new Reasoning($reasoning, $output);
            } catch (Throwable $e) {
                $lastException = $e;

                if ($attempt < $this->maxRetries) {
                    $messages[] = ['role' => 'assistant', 'content' => $response];
                    $messages[] = ['role' => 'user', 'content' => "The previous response could not be parsed. Error: {$e->getMessage()}\nPlease try again following the format exactly."];
                }
            }
        }

        throw new PredictException(
            rawResponse: $this->lm->getHistory()[count($this->lm->getHistory()) - 1]['response'] ?? '',
            attempts: $this->maxRetries,
            signatureClass: $this->signature,
            message: 'Failed to parse LLM response after '.$this->maxRetries.' attempt(s): '.$lastException?->getMessage(),
            previous: $lastException,
        );
    }
}
