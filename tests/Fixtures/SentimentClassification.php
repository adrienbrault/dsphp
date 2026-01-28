<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests\Fixtures;

use AdrienBrault\DsPhp\InputField;
use AdrienBrault\DsPhp\OutputField;

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
