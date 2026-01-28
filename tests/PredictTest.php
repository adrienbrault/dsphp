<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests;

use AdrienBrault\DsPhp\Adapter;
use AdrienBrault\DsPhp\LM;
use AdrienBrault\DsPhp\Predict;
use AdrienBrault\DsPhp\PredictException;
use AdrienBrault\DsPhp\Tests\Fixtures\BasicQA;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

final class PredictTest extends TestCase
{
    #[Test]
    public function itCallsLmAndReturnsPopulatedSignature(): void
    {
        $lm = $this->createLMStub("[[ ## answer ## ]]\nParis");
        $predict = new Predict(BasicQA::class, $lm);
        $result = ($predict)(new BasicQA(question: 'What is the capital of France?'));

        self::assertSame('What is the capital of France?', $result->question);
        self::assertSame('Paris', $result->answer);
    }

    #[Test]
    public function itPassesDemosToAdapter(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects(self::once())
            ->method('formatMessages')
            ->with(
                BasicQA::class,
                [['question' => 'Q1', 'answer' => 'A1']],
                ['question' => 'test'],
            )
            ->willReturn([['role' => 'user', 'content' => 'test']])
        ;
        $adapter->method('getOptions')->willReturn([]);
        $adapter->method('parseResponse')->willReturn(['answer' => 'ok']);

        $lm = $this->createLMStub('anything');
        $predict = new Predict(BasicQA::class, $lm, $adapter);
        $predict->demos = [['question' => 'Q1', 'answer' => 'A1']];

        ($predict)(new BasicQA(question: 'test'));
    }

    #[Test]
    public function itReturnsSignatureClass(): void
    {
        $lm = $this->createLMStub('');
        $predict = new Predict(BasicQA::class, $lm);
        self::assertSame(BasicQA::class, $predict->getSignature());
    }

    #[Test]
    public function itRetriesOnParseFailure(): void
    {
        $callCount = 0;
        $adapter = $this->createMock(Adapter::class);
        $adapter->method('formatMessages')->willReturn([['role' => 'user', 'content' => 'test']]);
        $adapter->method('getOptions')->willReturn([]);
        $adapter->method('parseResponse')
            ->willReturnCallback(static function () use (&$callCount): array {
                ++$callCount;
                if (1 === $callCount) {
                    throw new RuntimeException('parse error');
                }

                return ['answer' => 'ok'];
            })
        ;

        $lm = $this->createLMStub('response');
        $predict = new Predict(BasicQA::class, $lm, $adapter, maxRetries: 3);

        $result = ($predict)(new BasicQA(question: 'test'));
        self::assertSame('ok', $result->answer);
    }

    #[Test]
    public function itThrowsPredictExceptionAfterMaxRetries(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->method('formatMessages')->willReturn([['role' => 'user', 'content' => 'test']]);
        $adapter->method('getOptions')->willReturn([]);
        $adapter->method('parseResponse')->willThrowException(new RuntimeException('parse error'));

        $lm = $this->createLMStub('bad response');
        $predict = new Predict(BasicQA::class, $lm, $adapter, maxRetries: 2);

        $this->expectException(PredictException::class);
        ($predict)(new BasicQA(question: 'test'));
    }

    #[Test]
    public function predictExceptionHasCorrectProperties(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->method('formatMessages')->willReturn([['role' => 'user', 'content' => 'test']]);
        $adapter->method('getOptions')->willReturn([]);
        $adapter->method('parseResponse')->willThrowException(new RuntimeException('parse error'));

        $lm = $this->createLMStub('bad response');
        $predict = new Predict(BasicQA::class, $lm, $adapter, maxRetries: 2);

        try {
            ($predict)(new BasicQA(question: 'test'));
            self::fail('Expected PredictException');
        } catch (PredictException $e) {
            self::assertSame('bad response', $e->rawResponse);
            self::assertSame(2, $e->attempts);
            self::assertSame(BasicQA::class, $e->signatureClass);
        }
    }

    private function createLMStub(string $response): LM
    {
        $converter = $this->createMock(ResultConverterInterface::class);
        $converter->method('convert')->willReturn(new TextResult($response));
        $converter->method('getTokenUsageExtractor')->willReturn(null);
        $rawResult = $this->createMock(RawResultInterface::class);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('invoke')
            ->willReturn(new DeferredResult($converter, $rawResult))
        ;

        return new LM($platform, 'test-model');
    }
}
