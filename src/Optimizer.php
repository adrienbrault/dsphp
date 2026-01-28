<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

interface Optimizer
{
    /**
     * @template T of object
     *
     * @param T $student Any callable object with Predict properties
     * @param list<object> $trainset Signature instances with ground-truth values
     *
     * @return T Optimized clone with populated demos
     */
    public function compile(object $student, array $trainset): object;
}
