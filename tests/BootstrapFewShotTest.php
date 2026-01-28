<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests;

use AdrienBrault\DsPhp\BootstrapFewShot;
use AdrienBrault\DsPhp\LM;
use AdrienBrault\DsPhp\Metrics;
use AdrienBrault\DsPhp\ModuleUtils;
use AdrienBrault\DsPhp\Predict;
use AdrienBrault\DsPhp\Tests\Fixtures\BasicQA;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

use function array_values;

final class BootstrapFewShotTest extends TestCase
{
    #[Test]
    public function itReturnsACloneNotTheOriginal(): void
    {
        $lm = $this->createLMStub("[[ ## answer ## ]]\nParis");
        $student = new class($lm) {
            /** @var Predict<BasicQA> */
            public Predict $predict;

            public function __construct(LM $lm)
            {
                $this->predict = new Predict(BasicQA::class, $lm);
            }

            public function __invoke(BasicQA $input): BasicQA
            {
                return ($this->predict)($input);
            }
        };

        $trainset = [
            new BasicQA(question: 'Capital of France?', answer: 'Paris'),
        ];

        $optimizer = new BootstrapFewShot(metric: Metrics::exactMatch(), maxBootstrappedDemos: 2);
        $compiled = $optimizer->compile($student, $trainset);

        self::assertNotSame($student, $compiled);
    }

    #[Test]
    public function itPopulatesDemosOnPredictInstances(): void
    {
        $lm = $this->createLMStub("[[ ## answer ## ]]\nParis");
        $student = new class($lm) {
            /** @var Predict<BasicQA> */
            public Predict $predict;

            public function __construct(LM $lm)
            {
                $this->predict = new Predict(BasicQA::class, $lm);
            }

            public function __invoke(BasicQA $input): BasicQA
            {
                return ($this->predict)($input);
            }
        };

        $trainset = [
            new BasicQA(question: 'Capital of France?', answer: 'Paris'),
        ];

        $optimizer = new BootstrapFewShot(metric: Metrics::exactMatch(), maxBootstrappedDemos: 2);
        $compiled = $optimizer->compile($student, $trainset);

        $predictInstances = ModuleUtils::findPredictInstances($compiled);
        self::assertNotEmpty($predictInstances);
        $firstPredict = array_values($predictInstances)[0];
        self::assertNotEmpty($firstPredict->demos);
    }

    #[Test]
    public function itDoesNotModifyOriginalStudent(): void
    {
        $lm = $this->createLMStub("[[ ## answer ## ]]\nParis");
        $student = new class($lm) {
            /** @var Predict<BasicQA> */
            public Predict $predict;

            public function __construct(LM $lm)
            {
                $this->predict = new Predict(BasicQA::class, $lm);
            }

            public function __invoke(BasicQA $input): BasicQA
            {
                return ($this->predict)($input);
            }
        };

        $trainset = [
            new BasicQA(question: 'Capital of France?', answer: 'Paris'),
        ];

        $optimizer = new BootstrapFewShot(metric: Metrics::exactMatch(), maxBootstrappedDemos: 2);
        $optimizer->compile($student, $trainset);

        // Original student's predict should have no demos
        self::assertSame([], $student->predict->demos);
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
