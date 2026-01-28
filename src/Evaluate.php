<?php

declare(strict_types=1);

namespace DSPy;

use Closure;

use function assert;
use function count;
use function is_object;

final class Evaluate
{
    /**
     * @param list<object> $dataset Signature instances with ground-truth values
     * @param Closure(object, object): (bool|float) $metric
     */
    public function __construct(
        private readonly array $dataset,
        private readonly Closure $metric,
    ) {}

    /**
     * Run the module on each example and return the average metric score.
     */
    public function __invoke(callable $module): float
    {
        if (0 === count($this->dataset)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($this->dataset as $example) {
            $prediction = $module($example);
            assert(is_object($prediction));
            $score = ($this->metric)($example, $prediction);
            $total += true === $score ? 1.0 : (false === $score ? 0.0 : $score);
        }

        return $total / count($this->dataset);
    }
}
