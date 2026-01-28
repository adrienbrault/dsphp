<?php

declare(strict_types=1);

namespace DSPy\Tests\Fixtures;

use DSPy\InputField;
use DSPy\OutputField;

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
