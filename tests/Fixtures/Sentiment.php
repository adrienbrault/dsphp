<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests\Fixtures;

enum Sentiment: string
{
    case Positive = 'positive';
    case Negative = 'negative';
    case Neutral = 'neutral';
}
