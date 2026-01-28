<?php

declare(strict_types=1);

namespace DSPy;

use Closure;
use Throwable;

use function assert;
use function is_callable;
use function shuffle;

final class BootstrapFewShotWithRandomSearch implements Optimizer
{
    /**
     * @param Closure(object, object): (bool|float) $metric
     */
    public function __construct(
        private readonly Closure $metric,
        private readonly int $maxBootstrappedDemos = 4,
        private readonly int $maxLabeledDemos = 16,
        private readonly int $numCandidatePrograms = 10,
    ) {}

    public function compile(object $student, array $trainset): object
    {
        $bestScore = -1.0;
        $bestProgram = ModuleUtils::deepClone($student);

        $evaluator = new Evaluate($trainset, $this->metric);

        for ($i = 0; $i < $this->numCandidatePrograms; ++$i) {
            // Shuffle trainset for this candidate
            $shuffled = $trainset;
            shuffle($shuffled);

            // Bootstrap with different random subsets
            $bootstrap = new BootstrapFewShot(
                metric: $this->metric,
                maxBootstrappedDemos: $this->maxBootstrappedDemos,
                maxLabeledDemos: $this->maxLabeledDemos,
            );

            try {
                $candidate = $bootstrap->compile($student, $shuffled);
                assert(is_callable($candidate));
                $score = $evaluator($candidate);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestProgram = $candidate;
                }
            } catch (Throwable) {
                // Skip failed candidates
                continue;
            }
        }

        return $bestProgram;
    }
}
