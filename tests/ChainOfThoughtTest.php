<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests;

use AdrienBrault\DsPhp\ChainOfThought;
use AdrienBrault\DsPhp\LM;
use AdrienBrault\DsPhp\Tests\Fixtures\BasicQA;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

final class ChainOfThoughtTest extends TestCase
{
    #[Test]
    public function itReturnsReasoningWithOutput(): void
    {
        $response = "[[ ## reasoning ## ]]\nLet me think. France's capital is Paris.\n\n[[ ## answer ## ]]\nParis";
        $lm = $this->createLMStub($response);
        $cot = new ChainOfThought(BasicQA::class, $lm);

        $result = ($cot)(new BasicQA(question: 'What is the capital of France?'));

        self::assertStringContainsString('Let me think', $result->reasoning);
        self::assertSame('Paris', $result->output->answer);
    }

    #[Test]
    public function itForwardsOutputPropertiesViaMixin(): void
    {
        $response = "[[ ## reasoning ## ]]\nThinking...\n\n[[ ## answer ## ]]\n42";
        $lm = $this->createLMStub($response);
        $cot = new ChainOfThought(BasicQA::class, $lm);

        $result = ($cot)(new BasicQA(question: 'What is the answer?'));

        self::assertSame('42', $result->answer);
    }

    #[Test]
    public function itExposesPredictInstance(): void
    {
        $lm = $this->createLMStub('');
        $cot = new ChainOfThought(BasicQA::class, $lm);

        self::assertSame(BasicQA::class, $cot->predict->getSignature());
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
