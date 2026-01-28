<?php

declare(strict_types=1);

namespace DSPy\Tests;

use DSPy\JsonAdapter;
use DSPy\Tests\Fixtures\BasicQA;
use DSPy\Tests\Fixtures\CheckCitationFaithfulness;
use DSPy\Tests\Fixtures\Sentiment;
use DSPy\Tests\Fixtures\SentimentClassification;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;
use function json_decode;

final class JsonAdapterTest extends TestCase
{
    #[Test]
    public function itFormatsSystemMessageWithJsonInstructions(): void
    {
        $adapter = new JsonAdapter();
        $messages = $adapter->formatMessages(BasicQA::class, [], ['question' => 'What is PHP?']);

        self::assertNotEmpty($messages);
        self::assertSame('system', $messages[0]['role']);
        self::assertStringContainsString('JSON object', $messages[0]['content']);
        self::assertStringContainsString('"answer"', $messages[0]['content']);
    }

    #[Test]
    public function itFormatsUserInputAsJson(): void
    {
        $adapter = new JsonAdapter();
        $messages = $adapter->formatMessages(BasicQA::class, [], ['question' => 'What is PHP?']);

        self::assertNotEmpty($messages);
        $last = $messages[count($messages) - 1];
        self::assertSame('user', $last['role']);
        $decoded = json_decode($last['content'], true);
        self::assertSame(['question' => 'What is PHP?'], $decoded);
    }

    #[Test]
    public function itReturnsResponseFormatOptions(): void
    {
        $adapter = new JsonAdapter();
        $options = $adapter->getOptions(BasicQA::class);

        self::assertArrayHasKey('response_format', $options);
        // @phpstan-ignore-next-line
        self::assertSame('json_schema', $options['response_format']['type']);
    }

    #[Test]
    public function itBuildsJsonSchemaForOutputFields(): void
    {
        $adapter = new JsonAdapter();
        $options = $adapter->getOptions(CheckCitationFaithfulness::class);

        /** @phpstan-ignore-next-line */
        $schema = $options['response_format']['json_schema']['schema'];
        // @phpstan-ignore-next-line
        self::assertSame('boolean', $schema['properties']['faithful']['type']);
        // @phpstan-ignore-next-line
        self::assertSame('array', $schema['properties']['evidence']['type']);
    }

    #[Test]
    public function itParsesJsonResponse(): void
    {
        $adapter = new JsonAdapter();
        $response = '{"answer": "Paris"}';

        $values = $adapter->parseResponse(BasicQA::class, $response);
        self::assertSame(['answer' => 'Paris'], $values);
    }

    #[Test]
    public function itParsesJsonResponseWithBool(): void
    {
        $adapter = new JsonAdapter();
        $response = '{"faithful": true, "evidence": ["excerpt one"]}';

        $values = $adapter->parseResponse(CheckCitationFaithfulness::class, $response);
        self::assertArrayHasKey('faithful', $values);
        self::assertArrayHasKey('evidence', $values);
        self::assertTrue($values['faithful']);
        self::assertSame(['excerpt one'], $values['evidence']);
    }

    #[Test]
    public function itParsesJsonResponseWithEnum(): void
    {
        $adapter = new JsonAdapter();
        $response = '{"sentiment": "positive"}';

        $values = $adapter->parseResponse(SentimentClassification::class, $response);
        self::assertArrayHasKey('sentiment', $values);
        self::assertSame(Sentiment::Positive, $values['sentiment']);
    }
}
