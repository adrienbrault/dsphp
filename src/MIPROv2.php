<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

use Closure;

/**
 * Simplified MIPROv2 approximation using bootstrap few-shot with random search.
 *
 * @experimental This is NOT a full MIPROv2 implementation. The real MIPROv2
 *     performs instruction generation and prompt optimization alongside
 *     bootstrap few-shot. This class only delegates to
 *     BootstrapFewShotWithRandomSearch with budget presets matching the
 *     'auto' parameter. A full implementation would require instruction
 *     proposal generation and Bayesian optimization over prompt candidates.
 */
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
        $optimizer = new BootstrapFewShotWithRandomSearch(
            metric: $this->metric,
            maxBootstrappedDemos: $this->maxBootstrappedDemos,
            maxLabeledDemos: $this->maxLabeledDemos,
            numCandidatePrograms: $this->numCandidates,
        );

        return $optimizer->compile($student, $trainset);
    }
}
