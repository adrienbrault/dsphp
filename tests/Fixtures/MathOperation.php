<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests\Fixtures;

use AdrienBrault\DsPhp\InputField;
use AdrienBrault\DsPhp\OutputField;

/**
 * Evaluate expressions with operators like + - * /
 */
class MathOperation
{
    public function __construct(
        #[InputField(desc: 'math expression')]
        public readonly string $expression,
        #[OutputField(desc: 'numeric result')]
        public readonly string $result = '',
    ) {}
}
