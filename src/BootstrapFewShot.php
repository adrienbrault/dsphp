<?php

declare(strict_types=1);

namespace DSPy;

use Closure;
use Throwable;

use function array_merge;
use function array_slice;
use function assert;
use function count;
use function is_bool;
use function is_callable;
use function is_object;

/**
 * Bootstrap few-shot demonstrations from a teacher model.
 *
 * 1. Deep-clones the student (all Predict instances are fresh copies)
 * 2. Runs the teacher (or student copy) on training examples
 * 3. Filters traces by metric score
 * 4. Sets passing traces as demos on each Predict instance
 */
final class BootstrapFewShot implements Optimizer
{
    /**
     * @param Closure(object, object): (bool|float) $metric
     */
    public function __construct(
        private readonly Closure $metric,
        private readonly int $maxBootstrappedDemos = 4,
        private readonly int $maxLabeledDemos = 16,
        private readonly int $maxRounds = 1,
        private readonly ?object $teacher = null,
    ) {}

    public function compile(object $student, array $trainset): object
    {
        $compiled = ModuleUtils::deepClone($student);
        $teacher = $this->teacher ?? $student;

        $predictInstances = ModuleUtils::findPredictInstances($compiled);

        // Collect bootstrapped demos
        /** @var list<array<string, mixed>> $bootstrappedDemos */
        $bootstrappedDemos = [];

        for ($round = 0; $round < $this->maxRounds; ++$round) {
            foreach ($trainset as $example) {
                if (count($bootstrappedDemos) >= $this->maxBootstrappedDemos) {
                    break 2;
                }

                try {
                    assert(is_callable($teacher));
                    $prediction = $teacher($example);
                    assert(is_object($prediction));
                    $score = ($this->metric)($example, $prediction);
                    $passed = is_bool($score) ? $score : $score >= 0.5;

                    if ($passed) {
                        // Extract the trace as a demo
                        $demo = SignatureReflection::getAllFieldValues($example);
                        if ($prediction instanceof Reasoning) {
                            $predValues = SignatureReflection::getAllFieldValues($prediction->output);
                            if ('' !== $prediction->reasoning) {
                                $demo['reasoning'] = $prediction->reasoning;
                            }
                        } else {
                            $predValues = SignatureReflection::getAllFieldValues($prediction);
                        }
                        $demo = array_merge($demo, $predValues);
                        $bootstrappedDemos[] = $demo;
                    }
                } catch (Throwable) {
                    // Skip failed examples
                    continue;
                }
            }
        }

        // Assign demos to each Predict instance
        foreach ($predictInstances as $predict) {
            // Add labeled demos (from trainset)
            $labeledDemos = [];
            foreach (array_slice($trainset, 0, $this->maxLabeledDemos) as $example) {
                $labeledDemos[] = SignatureReflection::getAllFieldValues($example);
            }

            // Combine labeled + bootstrapped demos
            $predict->demos = array_merge(
                $labeledDemos,
                array_slice($bootstrappedDemos, 0, $this->maxBootstrappedDemos),
            );
        }

        return $compiled;
    }
}
