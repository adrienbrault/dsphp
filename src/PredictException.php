<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

use RuntimeException;
use Throwable;

final class PredictException extends RuntimeException
{
    /**
     * @param class-string $signatureClass
     */
    public function __construct(
        public readonly string $rawResponse,
        public readonly int $attempts,
        public readonly string $signatureClass,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
