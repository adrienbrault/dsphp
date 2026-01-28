<?php

declare(strict_types=1);

namespace DSPy\Tests;

use DSPy\Metrics;
use DSPy\Tests\Fixtures\BasicQA;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MetricsTest extends TestCase
{
    #[Test]
    public function exactMatchReturnsTrueForIdenticalAnswers(): void
    {
        $metric = Metrics::exactMatch();
        $example = new BasicQA(question: 'q', answer: 'Paris');
        $prediction = new BasicQA(question: 'q', answer: 'Paris');

        self::assertTrue($metric($example, $prediction));
    }

    #[Test]
    public function exactMatchIsCaseInsensitive(): void
    {
        $metric = Metrics::exactMatch();
        $example = new BasicQA(question: 'q', answer: 'Paris');
        $prediction = new BasicQA(question: 'q', answer: 'paris');

        self::assertTrue($metric($example, $prediction));
    }

    #[Test]
    public function exactMatchReturnsFalseForDifferentAnswers(): void
    {
        $metric = Metrics::exactMatch();
        $example = new BasicQA(question: 'q', answer: 'Paris');
        $prediction = new BasicQA(question: 'q', answer: 'London');

        self::assertFalse($metric($example, $prediction));
    }

    #[Test]
    public function exactMatchUsesCustomField(): void
    {
        $metric = Metrics::exactMatch('question');
        $example = new BasicQA(question: 'What?', answer: '');
        $prediction = new BasicQA(question: 'what?', answer: '');

        self::assertTrue($metric($example, $prediction));
    }

    #[Test]
    public function f1Returns1ForIdenticalAnswers(): void
    {
        $metric = Metrics::f1();
        $example = new BasicQA(question: 'q', answer: 'the quick brown fox');
        $prediction = new BasicQA(question: 'q', answer: 'the quick brown fox');

        self::assertSame(1.0, $metric($example, $prediction));
    }

    #[Test]
    public function f1Returns0ForCompletelyDifferentAnswers(): void
    {
        $metric = Metrics::f1();
        $example = new BasicQA(question: 'q', answer: 'hello world');
        $prediction = new BasicQA(question: 'q', answer: 'foo bar');

        self::assertSame(0.0, $metric($example, $prediction));
    }

    #[Test]
    public function f1ReturnsPartialOverlap(): void
    {
        $metric = Metrics::f1();
        $example = new BasicQA(question: 'q', answer: 'the quick brown fox');
        $prediction = new BasicQA(question: 'q', answer: 'the quick red fox');

        // Overlap: "the", "quick", "fox" = 3 common
        // Precision: 3/4, Recall: 3/4, F1 = 2 * (3/4 * 3/4) / (3/4 + 3/4) = 0.75
        self::assertEqualsWithDelta(0.75, $metric($example, $prediction), 0.01);
    }

    #[Test]
    public function f1HandlesEmptyStrings(): void
    {
        $metric = Metrics::f1();
        $example = new BasicQA(question: 'q', answer: '');
        $prediction = new BasicQA(question: 'q', answer: '');

        self::assertSame(0.0, $metric($example, $prediction));
    }
}
