<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests\Fixtures;

use AdrienBrault\DsPhp\InputField;
use AdrienBrault\DsPhp\OutputField;

/** Answer questions using retrieved context. */
class GenerateAnswer
{
    public function __construct(
        /** @var list<string> */
        #[InputField(desc: 'retrieved passages')]
        public readonly array $context,
        #[InputField]
        public readonly string $question,
        #[OutputField(desc: 'concise factual answer')]
        public readonly string $answer = '',
    ) {}
}
