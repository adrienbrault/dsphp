<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

use function array_merge;

final class LM
{
    /** @var list<array{messages: list<array{role: string, content: string}>, response: string}> */
    private array $history = [];

    /**
     * @param array<string, mixed> $options Default options (temperature, max_tokens, etc.)
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        /** @var non-empty-string */
        private readonly string $model,
        private readonly array $options = [],
    ) {}

    /**
     * @param list<array{role: string, content: string}> $messages
     * @param array<string, mixed> $options Per-call overrides
     */
    public function chat(array $messages, array $options = []): string
    {
        $messageBag = new MessageBag();
        foreach ($messages as $msg) {
            $messageBag = match ($msg['role']) {
                'system' => $messageBag->with(Message::forSystem($msg['content'])),
                'assistant' => $messageBag->with(Message::ofAssistant($msg['content'])),
                default => $messageBag->with(Message::ofUser($msg['content'])),
            };
        }

        $mergedOptions = array_merge($this->options, $options);
        $deferred = $this->platform->invoke($this->model, $messageBag, $mergedOptions);

        $text = $deferred->asText();

        $this->history[] = [
            'messages' => $messages,
            'response' => $text,
        ];

        return $text;
    }

    /**
     * @return list<array{messages: list<array{role: string, content: string}>, response: string}>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
