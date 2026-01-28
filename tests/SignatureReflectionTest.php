<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests;

use AdrienBrault\DsPhp\SignatureReflection;
use AdrienBrault\DsPhp\Tests\Fixtures\BasicQA;
use AdrienBrault\DsPhp\Tests\Fixtures\CheckCitationFaithfulness;
use AdrienBrault\DsPhp\Tests\Fixtures\GenerateAnswer;
use AdrienBrault\DsPhp\Tests\Fixtures\MathOperation;
use AdrienBrault\DsPhp\Tests\Fixtures\Sentiment;
use AdrienBrault\DsPhp\Tests\Fixtures\SentimentClassification;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SignatureReflectionTest extends TestCase
{
    #[Test]
    public function itExtractsTaskInstructionFromDocblock(): void
    {
        self::assertSame(
            'Answer questions with short factoid answers.',
            SignatureReflection::getTaskInstruction(BasicQA::class),
        );
    }

    #[Test]
    public function itReturnsEmptyInstructionWithoutDocblock(): void
    {
        $class = new class('') {
            public function __construct(public readonly string $x) {}
        };
        self::assertSame('', SignatureReflection::getTaskInstruction($class::class));
    }

    #[Test]
    public function itGetsInputFields(): void
    {
        $fields = SignatureReflection::getInputFields(BasicQA::class);
        self::assertCount(1, $fields);
        self::assertSame('question', $fields[0]['name']);
        self::assertSame('string', $fields[0]['type']);
        self::assertSame('question to answer', $fields[0]['desc']);
    }

    #[Test]
    public function itGetsOutputFields(): void
    {
        $fields = SignatureReflection::getOutputFields(BasicQA::class);
        self::assertCount(1, $fields);
        self::assertSame('answer', $fields[0]['name']);
        self::assertSame('string', $fields[0]['type']);
        self::assertSame('often between 1 and 5 words', $fields[0]['desc']);
    }

    #[Test]
    public function itHandlesEnumType(): void
    {
        $fields = SignatureReflection::getOutputFields(SentimentClassification::class);
        self::assertCount(1, $fields);
        self::assertSame('sentiment', $fields[0]['name']);
        self::assertStringContainsString('positive', $fields[0]['type']);
        self::assertStringContainsString('negative', $fields[0]['type']);
        self::assertStringContainsString('neutral', $fields[0]['type']);
    }

    #[Test]
    public function itHandlesBoolType(): void
    {
        $fields = SignatureReflection::getOutputFields(CheckCitationFaithfulness::class);
        $faithfulField = null;
        foreach ($fields as $field) {
            if ('faithful' === $field['name']) {
                $faithfulField = $field;
            }
        }
        self::assertNotNull($faithfulField);
        self::assertSame('bool', $faithfulField['type']);
    }

    #[Test]
    public function itHandlesArrayType(): void
    {
        $fields = SignatureReflection::getOutputFields(CheckCitationFaithfulness::class);
        $evidenceField = null;
        foreach ($fields as $field) {
            if ('evidence' === $field['name']) {
                $evidenceField = $field;
            }
        }
        self::assertNotNull($evidenceField);
        self::assertSame('list<string>', $evidenceField['type']);
    }

    #[Test]
    public function itExtractsInputValues(): void
    {
        $qa = new BasicQA(question: 'What is PHP?');
        $values = SignatureReflection::getInputValues($qa);
        self::assertSame(['question' => 'What is PHP?'], $values);
    }

    #[Test]
    public function itExtractsAllFieldValues(): void
    {
        $qa = new BasicQA(question: 'What is PHP?', answer: 'A language');
        $values = SignatureReflection::getAllFieldValues($qa);
        self::assertSame(['question' => 'What is PHP?', 'answer' => 'A language'], $values);
    }

    #[Test]
    public function itHandlesMultipleInputFields(): void
    {
        $fields = SignatureReflection::getInputFields(CheckCitationFaithfulness::class);
        self::assertCount(2, $fields);
        self::assertSame('context', $fields[0]['name']);
        self::assertSame('text', $fields[1]['name']);
    }

    #[Test]
    public function itHandlesArrayInputField(): void
    {
        $fields = SignatureReflection::getInputFields(GenerateAnswer::class);
        $contextField = null;
        foreach ($fields as $field) {
            if ('context' === $field['name']) {
                $contextField = $field;
            }
        }
        self::assertNotNull($contextField);
        self::assertSame('list<string>', $contextField['type']);
    }

    #[Test]
    public function itCastsStringValue(): void
    {
        $value = SignatureReflection::castValue('hello', BasicQA::class, 'answer');
        self::assertSame('hello', $value);
    }

    #[Test]
    public function itCastsBoolValue(): void
    {
        $value = SignatureReflection::castValue('true', CheckCitationFaithfulness::class, 'faithful');
        self::assertTrue($value);

        $value = SignatureReflection::castValue('false', CheckCitationFaithfulness::class, 'faithful');
        self::assertFalse($value);
    }

    #[Test]
    public function itCastsEnumValue(): void
    {
        $value = SignatureReflection::castValue('positive', SentimentClassification::class, 'sentiment');
        self::assertSame(Sentiment::Positive, $value);
    }

    #[Test]
    public function itCastsArrayValueFromJson(): void
    {
        $value = SignatureReflection::castValue(
            '["excerpt one", "excerpt two"]',
            CheckCitationFaithfulness::class,
            'evidence',
        );
        self::assertSame(['excerpt one', 'excerpt two'], $value);
    }

    #[Test]
    public function itPreservesSpecialCharactersInDocblock(): void
    {
        // Docblock: "Evaluate expressions with operators like + - * /"
        // rtrim($line, '* /') treats chars as a set, stripping trailing * and /
        // that are part of the content, not just comment markers.
        $instruction = SignatureReflection::getTaskInstruction(MathOperation::class);
        self::assertSame('Evaluate expressions with operators like + - * /', $instruction);
    }
}
