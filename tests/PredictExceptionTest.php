<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests;

use AdrienBrault\DsPhp\PredictException;
use AdrienBrault\DsPhp\Tests\Fixtures\BasicQA;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PredictExceptionTest extends TestCase
{
    #[Test]
    public function itStoresRawResponse(): void
    {
        $e = new PredictException(
            rawResponse: 'some garbage',
            attempts: 3,
            signatureClass: BasicQA::class,
            message: 'Failed to parse',
        );
        self::assertSame('some garbage', $e->rawResponse);
    }

    #[Test]
    public function itStoresAttempts(): void
    {
        $e = new PredictException(
            rawResponse: '',
            attempts: 5,
            signatureClass: BasicQA::class,
        );
        self::assertSame(5, $e->attempts);
    }

    #[Test]
    public function itStoresSignatureClass(): void
    {
        $e = new PredictException(
            rawResponse: '',
            attempts: 1,
            signatureClass: BasicQA::class,
        );
        self::assertSame(BasicQA::class, $e->signatureClass);
    }

    #[Test]
    public function itIsARuntimeException(): void
    {
        $e = new PredictException(
            rawResponse: '',
            attempts: 1,
            signatureClass: BasicQA::class,
            message: 'Parse failed',
        );
        // @phpstan-ignore-next-line
        self::assertInstanceOf(RuntimeException::class, $e);
        self::assertSame('Parse failed', $e->getMessage());
    }

    #[Test]
    public function itSupportsPreviousException(): void
    {
        $prev = new RuntimeException('inner');
        $e = new PredictException(
            rawResponse: '',
            attempts: 1,
            signatureClass: BasicQA::class,
            previous: $prev,
        );
        self::assertSame($prev, $e->getPrevious());
    }
}
