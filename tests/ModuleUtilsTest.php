<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests;

use AdrienBrault\DsPhp\ChainOfThought;
use AdrienBrault\DsPhp\LM;
use AdrienBrault\DsPhp\ModuleUtils;
use AdrienBrault\DsPhp\Predict;
use AdrienBrault\DsPhp\Tests\Fixtures\BasicQA;
use AdrienBrault\DsPhp\Tests\Fixtures\GenerateAnswer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;

final class ModuleUtilsTest extends TestCase
{
    #[Test]
    public function itFindsDirectPredictInstances(): void
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

        $instances = ModuleUtils::findPredictInstances($module);
        self::assertCount(1, $instances);
        self::assertArrayHasKey('predict', $instances);
        self::assertSame(BasicQA::class, $instances['predict']->getSignature());
    }

    #[Test]
    public function itFindsChainOfThoughtPredictInstances(): void
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

        $instances = ModuleUtils::findPredictInstances($module);
        self::assertCount(1, $instances);
        self::assertArrayHasKey('cot.predict', $instances);
    }

    #[Test]
    public function itFindsMultiplePredictInstances(): void
    {
        $lm = $this->createLMStub();
        $module = new class($lm) {
            /** @var Predict<BasicQA> */
            public Predict $first;

            /** @var Predict<GenerateAnswer> */
            public Predict $second;

            public function __construct(LM $lm)
            {
                $this->first = new Predict(BasicQA::class, $lm);
                $this->second = new Predict(GenerateAnswer::class, $lm);
            }
        };

        $instances = ModuleUtils::findPredictInstances($module);
        self::assertCount(2, $instances);
        self::assertArrayHasKey('first', $instances);
        self::assertArrayHasKey('second', $instances);
    }

    #[Test]
    public function deepCloneCreatesSeparateInstances(): void
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
        $module->predict->demos = [['question' => 'q', 'answer' => 'a']];

        $clone = ModuleUtils::deepClone($module);

        self::assertNotSame($module, $clone);
        self::assertNotSame($module->predict, $clone->predict);
    }

    #[Test]
    public function deepCloneDoesNotShareDemos(): void
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
        $module->predict->demos = [['question' => 'q', 'answer' => 'a']];

        $clone = ModuleUtils::deepClone($module);
        $clone->predict->demos = [];

        // Original should be unaffected
        self::assertCount(1, $module->predict->demos);
    }

    #[Test]
    public function deepCloneClonesChainOfThought(): void
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

        $clone = ModuleUtils::deepClone($module);

        self::assertNotSame($module->cot, $clone->cot);
        self::assertNotSame($module->cot->predict, $clone->cot->predict);
    }

    #[Test]
    public function itReturnsEmptyForModuleWithoutPredictInstances(): void
    {
        $module = new class {
            public string $name = 'test';
        };

        $instances = ModuleUtils::findPredictInstances($module);
        self::assertSame([], $instances);
    }

    private function createLMStub(): LM
    {
        return new LM($this->createMock(PlatformInterface::class), 'test-model');
    }
}
