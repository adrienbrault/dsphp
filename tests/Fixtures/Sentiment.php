<?php

declare(strict_types=1);

namespace DSPy\Tests\Fixtures;

enum Sentiment: string
{
    case Positive = 'positive';
    case Negative = 'negative';
    case Neutral = 'neutral';
}
