<?php

declare(strict_types=1);

namespace DSPy\Tests\Fixtures;

use DSPy\InputField;
use DSPy\OutputField;

/** Classify the sentiment of a sentence. */
class SentimentClassification
{
    public function __construct(
        #[InputField]
        public readonly string $sentence,
        #[OutputField(desc: 'one of: positive, negative, neutral')]
        public readonly Sentiment $sentiment = Sentiment::Neutral,
    ) {}
}
