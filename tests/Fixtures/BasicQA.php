<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests\Fixtures;

use AdrienBrault\DsPhp\InputField;
use AdrienBrault\DsPhp\OutputField;

/** Answer questions with short factoid answers. */
class BasicQA
{
    public function __construct(
        #[InputField(desc: 'question to answer')]
        public readonly string $question,
        #[OutputField(desc: 'often between 1 and 5 words')]
        public readonly string $answer = '',
    ) {}
}
