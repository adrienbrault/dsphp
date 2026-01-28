<?php

declare(strict_types=1);

namespace DSPy\Tests;

use DSPy\ChatAdapter;
use DSPy\Tests\Fixtures\BasicQA;
use DSPy\Tests\Fixtures\CheckCitationFaithfulness;
use DSPy\Tests\Fixtures\Sentiment;
use DSPy\Tests\Fixtures\SentimentClassification;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

final class ChatAdapterTest extends TestCase
{
    #[Test]
    public function itFormatsSystemMessageWithInstructionAndFields(): void
    {
        $adapter = new ChatAdapter();
        $messages = $adapter->formatMessages(BasicQA::class, [], ['question' => 'What is PHP?']);

        self::assertNotEmpty($messages);
        self::assertSame('system', $messages[0]['role']);
        self::assertStringContainsString('Answer questions with short factoid answers.', $messages[0]['content']);
        self::assertStringContainsString('[[ ## question ## ]]', $messages[0]['content']);
        self::assertStringContainsString('[[ ## answer ## ]]', $messages[0]['content']);
    }

    #[Test]
    public function itFormatsUserInputWithMarkers(): void
    {
        $adapter = new ChatAdapter();
        $messages = $adapter->formatMessages(BasicQA::class, [], ['question' => 'What is PHP?']);

        // Last message should be user turn with input
        self::assertNotEmpty($messages);
        $last = $messages[count($messages) - 1];
        self::assertSame('user', $last['role']);
        self::assertStringContainsString('[[ ## question ## ]]', $last['content']);
        self::assertStringContainsString('What is PHP?', $last['content']);
    }

    #[Test]
    public function itFormatsDemosAsUserAssistantPairs(): void
    {
        $adapter = new ChatAdapter();
        $demos = [
            ['question' => 'What is 2+2?', 'answer' => '4'],
        ];
        $messages = $adapter->formatMessages(BasicQA::class, $demos, ['question' => 'What is 3+3?']);

        // system, demo user, demo assistant, current user
        self::assertCount(4, $messages);
        self::assertSame('system', $messages[0]['role']);
        self::assertSame('user', $messages[1]['role']);
        self::assertSame('assistant', $messages[2]['role']);
        self::assertSame('user', $messages[3]['role']);

        self::assertStringContainsString('What is 2+2?', $messages[1]['content']);
        self::assertStringContainsString('4', $messages[2]['content']);
    }

    #[Test]
    public function itReturnsEmptyOptions(): void
    {
        $adapter = new ChatAdapter();
        self::assertSame([], $adapter->getOptions(BasicQA::class));
    }

    #[Test]
    public function itParsesResponseWithMarkers(): void
    {
        $adapter = new ChatAdapter();
        $response = "[[ ## answer ## ]]\nParis";

        $values = $adapter->parseResponse(BasicQA::class, $response);
        self::assertSame(['answer' => 'Paris'], $values);
    }

    #[Test]
    public function itParsesMultipleOutputFields(): void
    {
        $adapter = new ChatAdapter();
        $response = "[[ ## faithful ## ]]\ntrue\n\n[[ ## evidence ## ]]\n[\"The sky is blue\"]";

        $values = $adapter->parseResponse(CheckCitationFaithfulness::class, $response);
        self::assertArrayHasKey('faithful', $values);
        self::assertArrayHasKey('evidence', $values);
        self::assertTrue($values['faithful']);
        self::assertSame(['The sky is blue'], $values['evidence']);
    }

    #[Test]
    public function itParsesEnumValue(): void
    {
        $adapter = new ChatAdapter();
        $response = "[[ ## sentiment ## ]]\npositive";

        $values = $adapter->parseResponse(SentimentClassification::class, $response);
        self::assertArrayHasKey('sentiment', $values);
        self::assertSame(Sentiment::Positive, $values['sentiment']);
    }

    #[Test]
    public function itParsesReasoningSection(): void
    {
        $adapter = new ChatAdapter();
        $response = "[[ ## reasoning ## ]]\nLet me think step by step.\n\n[[ ## answer ## ]]\nParis";

        $reasoning = $adapter->parseReasoning($response);
        self::assertSame('Let me think step by step.', $reasoning);
    }

    #[Test]
    public function itIncludesReasoningInDemoAssistantMessages(): void
    {
        $adapter = new ChatAdapter();
        $demos = [
            ['question' => 'What is 2+2?', 'answer' => '4', 'reasoning' => 'Adding numbers'],
        ];
        $messages = $adapter->formatMessages(BasicQA::class, $demos, ['question' => 'test']);

        self::assertArrayHasKey(2, $messages);
        $assistantMsg = $messages[2];
        self::assertStringContainsString('[[ ## reasoning ## ]]', $assistantMsg['content']);
        self::assertStringContainsString('Adding numbers', $assistantMsg['content']);
    }
}
