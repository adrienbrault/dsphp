<?php

declare(strict_types=1);

namespace DSPy;

use RuntimeException;
use Throwable;

use function count;

/**
 * @template T of object
 */
final class Predict
{
    /** @var list<array<string, mixed>> Few-shot demos (set by optimizers) */
    public array $demos = [];

    /**
     * @param class-string<T> $signature
     */
    public function __construct(
        private readonly string $signature,
        private readonly LM $lm,
        private readonly Adapter $adapter = new ChatAdapter(),
        private readonly int $maxRetries = 3,
    ) {}

    /**
     * Call the LM with the signature's input fields and return a fully populated instance.
     *
     * @param T $input
     *
     * @return T
     */
    public function __invoke(object $input): object
    {
        $inputValues = SignatureReflection::getInputValues($input);
        $messages = $this->adapter->formatMessages($this->signature, $this->demos, $inputValues);
        $options = $this->adapter->getOptions($this->signature);

        $lastResponse = '';
        for ($attempt = 1; $attempt <= $this->maxRetries; ++$attempt) {
            $response = $this->lm->chat($messages, $options);
            $lastResponse = $response;

            try {
                $outputValues = $this->adapter->parseResponse($this->signature, $response);

                if (0 === count($outputValues)) {
                    throw new RuntimeException('No output fields parsed from response');
                }

                // @var T
                return new ($this->signature)(...$inputValues, ...$outputValues);
            } catch (Throwable $e) {
                if ($attempt >= $this->maxRetries) {
                    throw new PredictException(
                        rawResponse: $lastResponse,
                        attempts: $attempt,
                        signatureClass: $this->signature,
                        message: 'Failed to parse LLM response after '.$attempt.' attempt(s): '.$e->getMessage(),
                        previous: $e,
                    );
                }

                // Append retry messages
                $messages[] = ['role' => 'assistant', 'content' => $response];
                $messages[] = ['role' => 'user', 'content' => "The previous response could not be parsed. Error: {$e->getMessage()}\nPlease try again following the format exactly."];
            }
        }

        // @codeCoverageIgnoreStart
        throw new PredictException(
            rawResponse: $lastResponse,
            attempts: $this->maxRetries,
            signatureClass: $this->signature,
            message: 'Failed to parse LLM response after '.$this->maxRetries.' attempt(s)',
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return class-string<T>
     */
    public function getSignature(): string
    {
        return $this->signature;
    }
}
