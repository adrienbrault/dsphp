<?php

declare(strict_types=1);

namespace DSPy\Tests;

use DSPy\LM;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

final class LMTest extends TestCase
{
    #[Test]
    public function itReturnsModelName(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $lm = new LM($platform, 'gpt-4o-mini');
        self::assertSame('gpt-4o-mini', $lm->getModel());
    }

    #[Test]
    public function itStartsWithEmptyHistory(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $lm = new LM($platform, 'gpt-4o-mini');
        self::assertSame([], $lm->getHistory());
    }

    #[Test]
    public function itCallsPlatformAndReturnsText(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())
            ->method('invoke')
            ->willReturn($this->createDeferredResult('Paris'))
        ;

        $lm = new LM($platform, 'gpt-4o-mini');
        $response = $lm->chat([
            ['role' => 'user', 'content' => 'What is the capital of France?'],
        ]);

        self::assertSame('Paris', $response);
    }

    #[Test]
    public function itRecordsHistory(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('invoke')
            ->willReturn($this->createDeferredResult('Paris'))
        ;

        $lm = new LM($platform, 'gpt-4o-mini');
        $messages = [
            ['role' => 'user', 'content' => 'What is the capital of France?'],
        ];
        $lm->chat($messages);

        $history = $lm->getHistory();
        self::assertCount(1, $history);
        self::assertSame($messages, $history[0]['messages']);
        self::assertSame('Paris', $history[0]['response']);
    }

    #[Test]
    public function itMergesOptions(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())
            ->method('invoke')
            ->willReturnCallback(function (string $model, mixed $input, array $options): DeferredResult {
                self::assertSame('gpt-4o-mini', $model);
                // @phpstan-ignore-next-line
                self::assertSame(0.7, $options['temperature']);
                // @phpstan-ignore-next-line
                self::assertSame(100, $options['max_tokens']);

                return $this->createDeferredResult('test');
            })
        ;

        $lm = new LM($platform, 'gpt-4o-mini', ['temperature' => 0.7]);
        $lm->chat(
            [['role' => 'user', 'content' => 'test']],
            ['max_tokens' => 100],
        );
    }

    private function createDeferredResult(string $text): DeferredResult
    {
        $converter = $this->createMock(ResultConverterInterface::class);
        $converter->method('convert')->willReturn(new TextResult($text));
        $converter->method('getTokenUsageExtractor')->willReturn(null);

        $rawResult = $this->createMock(RawResultInterface::class);

        return new DeferredResult($converter, $rawResult);
    }
}
