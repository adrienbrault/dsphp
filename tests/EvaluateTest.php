<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests;

use AdrienBrault\DsPhp\Evaluate;
use AdrienBrault\DsPhp\Metrics;
use AdrienBrault\DsPhp\Tests\Fixtures\BasicQA;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EvaluateTest extends TestCase
{
    #[Test]
    public function itReturnsPerfectScoreWhenAllMatch(): void
    {
        $dataset = [
            new BasicQA(question: 'q1', answer: 'Paris'),
            new BasicQA(question: 'q2', answer: 'London'),
        ];

        $module = static fn (BasicQA $input): BasicQA => new BasicQA(
            question: $input->question,
            answer: $input->answer,
        );

        $evaluator = new Evaluate($dataset, Metrics::exactMatch());
        $score = $evaluator($module);

        self::assertSame(1.0, $score);
    }

    #[Test]
    public function itReturnsZeroWhenNoneMatch(): void
    {
        $dataset = [
            new BasicQA(question: 'q1', answer: 'Paris'),
            new BasicQA(question: 'q2', answer: 'London'),
        ];

        $module = static fn (BasicQA $input): BasicQA => new BasicQA(
            question: $input->question,
            answer: 'wrong',
        );

        $evaluator = new Evaluate($dataset, Metrics::exactMatch());
        $score = $evaluator($module);

        self::assertSame(0.0, $score);
    }

    #[Test]
    public function itReturnsAverageScore(): void
    {
        $dataset = [
            new BasicQA(question: 'q1', answer: 'Paris'),
            new BasicQA(question: 'q2', answer: 'London'),
        ];

        $callCount = 0;
        $module = static function (BasicQA $input) use (&$callCount): BasicQA {
            ++$callCount;

            // First call returns correct answer, second returns wrong
            return new BasicQA(
                question: $input->question,
                answer: 1 === $callCount ? 'Paris' : 'wrong',
            );
        };

        $evaluator = new Evaluate($dataset, Metrics::exactMatch());
        $score = $evaluator($module);

        self::assertSame(0.5, $score);
    }

    #[Test]
    public function itHandlesFloatMetric(): void
    {
        $dataset = [
            new BasicQA(question: 'q', answer: 'the quick brown fox'),
        ];

        $module = static fn (BasicQA $input): BasicQA => new BasicQA(
            question: $input->question,
            answer: 'the quick red fox',
        );

        $evaluator = new Evaluate($dataset, Metrics::f1());
        $score = $evaluator($module);

        self::assertGreaterThan(0.0, $score);
        self::assertLessThan(1.0, $score);
    }

    #[Test]
    public function itHandlesEmptyDataset(): void
    {
        $evaluator = new Evaluate([], Metrics::exactMatch());
        $score = $evaluator(static fn (object $input): object => $input);

        self::assertSame(0.0, $score);
    }
}
