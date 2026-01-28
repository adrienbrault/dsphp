<?php

declare(strict_types=1);

namespace DSPy\Tests;

use DSPy\Reasoning;
use DSPy\Tests\Fixtures\BasicQA;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReasoningTest extends TestCase
{
    #[Test]
    public function itStoresReasoningAndOutput(): void
    {
        $output = new BasicQA(question: 'q', answer: 'a');
        $reasoning = new Reasoning('step by step', $output);

        self::assertSame('step by step', $reasoning->reasoning);
        self::assertSame($output, $reasoning->output);
    }

    #[Test]
    public function itForwardsPropertyAccessToOutput(): void
    {
        $output = new BasicQA(question: 'q', answer: 'a');
        $reasoning = new Reasoning('step by step', $output);

        self::assertSame('a', $reasoning->answer);
        self::assertSame('q', $reasoning->question);
    }

    #[Test]
    public function itSupportsIsset(): void
    {
        $output = new BasicQA(question: 'q', answer: 'a');
        $reasoning = new Reasoning('step by step', $output);

        self::assertTrue(isset($reasoning->answer));
    }
}
