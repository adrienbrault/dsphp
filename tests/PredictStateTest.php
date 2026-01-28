<?php

declare(strict_types=1);

namespace DSPy\Tests;

use DSPy\ChainOfThought;
use DSPy\LM;
use DSPy\Predict;
use DSPy\PredictState;
use DSPy\Tests\Fixtures\BasicQA;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;

use function json_decode;
use function json_encode;

final class PredictStateTest extends TestCase
{
    #[Test]
    public function itDumpsEmptyStateForModuleWithoutDemos(): void
    {
        $lm = $this->createLMStub();
        $module = new class($lm) {
            /** @var Predict<BasicQA> */
            public Predict $predict;

            public function __construct(LM $lm)
            {
                $this->predict = new Predict(BasicQA::class, $lm);
            }
        };

        $state = (new PredictState())->dump($module);
        self::assertSame(['predict' => []], $state);
    }

    #[Test]
    public function itDumpsStateWithDemos(): void
    {
        $lm = $this->createLMStub();
        $module = new class($lm) {
            /** @var Predict<BasicQA> */
            public Predict $predict;

            public function __construct(LM $lm)
            {
                $this->predict = new Predict(BasicQA::class, $lm);
            }
        };
        $module->predict->demos = [
            ['question' => 'q1', 'answer' => 'a1'],
        ];

        $state = (new PredictState())->dump($module);
        self::assertSame([
            'predict' => [['question' => 'q1', 'answer' => 'a1']],
        ], $state);
    }

    #[Test]
    public function itLoadsStateIntoModule(): void
    {
        $lm = $this->createLMStub();
        $module = new class($lm) {
            /** @var Predict<BasicQA> */
            public Predict $predict;

            public function __construct(LM $lm)
            {
                $this->predict = new Predict(BasicQA::class, $lm);
            }
        };

        $state = [
            'predict' => [['question' => 'q1', 'answer' => 'a1']],
        ];

        (new PredictState())->load($module, $state);
        self::assertCount(1, $module->predict->demos);
        self::assertArrayHasKey('question', $module->predict->demos[0]);
        self::assertSame('q1', $module->predict->demos[0]['question']);
    }

    #[Test]
    public function itHandlesChainOfThoughtModules(): void
    {
        $lm = $this->createLMStub();
        $module = new class($lm) {
            /** @var ChainOfThought<BasicQA> */
            public ChainOfThought $cot;

            public function __construct(LM $lm)
            {
                $this->cot = new ChainOfThought(BasicQA::class, $lm);
            }
        };
        $module->cot->predict->demos = [
            ['question' => 'q1', 'answer' => 'a1', 'reasoning' => 'because'],
        ];

        $state = (new PredictState())->dump($module);
        self::assertArrayHasKey('cot.predict', $state);

        // Load into fresh module
        $fresh = new class($lm) {
            /** @var ChainOfThought<BasicQA> */
            public ChainOfThought $cot;

            public function __construct(LM $lm)
            {
                $this->cot = new ChainOfThought(BasicQA::class, $lm);
            }
        };

        (new PredictState())->load($fresh, $state);
        self::assertCount(1, $fresh->cot->predict->demos);
    }

    #[Test]
    public function roundtripDumpAndLoad(): void
    {
        $lm = $this->createLMStub();
        $module = new class($lm) {
            /** @var Predict<BasicQA> */
            public Predict $predict;

            public function __construct(LM $lm)
            {
                $this->predict = new Predict(BasicQA::class, $lm);
            }
        };

        $demos = [
            ['question' => 'q1', 'answer' => 'a1'],
            ['question' => 'q2', 'answer' => 'a2'],
        ];
        $module->predict->demos = $demos;

        $predictState = new PredictState();
        $state = $predictState->dump($module);

        // Simulate JSON roundtrip
        $json = json_encode($state, JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> $restored */
        $restored = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // Load into fresh module
        $fresh = new class($lm) {
            /** @var Predict<BasicQA> */
            public Predict $predict;

            public function __construct(LM $lm)
            {
                $this->predict = new Predict(BasicQA::class, $lm);
            }
        };
        $predictState->load($fresh, $restored);

        self::assertSame($demos, $fresh->predict->demos);
    }

    private function createLMStub(): LM
    {
        $platform = $this->createMock(PlatformInterface::class);

        return new LM($platform, 'test-model');
    }
}
