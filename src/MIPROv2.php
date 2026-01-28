<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

use Closure;

final class MIPROv2 implements Optimizer
{
    private readonly int $numCandidates;
    private readonly int $maxBootstrappedDemos;
    private readonly int $maxLabeledDemos;

    /**
     * @param Closure(object, object): (bool|float) $metric
     * @param 'heavy'|'light'|'medium' $auto Search budget preset
     */
    public function __construct(
        private readonly Closure $metric,
        private readonly string $auto = 'medium',
    ) {
        [$this->numCandidates, $this->maxBootstrappedDemos, $this->maxLabeledDemos] = match ($this->auto) {
            'light' => [5, 2, 4],
            'medium' => [15, 4, 8],
            'heavy' => [30, 8, 16],
        };
    }

    public function compile(object $student, array $trainset): object
    {
        // MIPROv2 is an advanced meta-optimizer.
        // It combines instruction generation with bootstrap few-shot.
        // For this implementation, we use BootstrapFewShotWithRandomSearch
        // as the core search strategy, with budgets set by the 'auto' preset.

        $optimizer = new BootstrapFewShotWithRandomSearch(
            metric: $this->metric,
            maxBootstrappedDemos: $this->maxBootstrappedDemos,
            maxLabeledDemos: $this->maxLabeledDemos,
            numCandidatePrograms: $this->numCandidates,
        );

        return $optimizer->compile($student, $trainset);
    }
}
