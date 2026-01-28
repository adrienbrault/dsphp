<?php

declare(strict_types=1);

namespace DSPy;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class OutputField
{
    public function __construct(
        public readonly string $desc = '',
    ) {}
}
