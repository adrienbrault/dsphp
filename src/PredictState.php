<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

use function is_array;

/**
 * Injectable service for saving and loading optimized Predict demos.
 */
final class PredictState
{
    /**
     * Extract the learnable state (demos) from all Predict instances in an object.
     *
     * @return array<string, mixed>
     */
    public function dump(object $module): array
    {
        $predictInstances = ModuleUtils::findPredictInstances($module);
        $state = [];

        foreach ($predictInstances as $path => $predict) {
            $state[$path] = $predict->demos;
        }

        return $state;
    }

    /**
     * Restore demos onto Predict instances in a module.
     *
     * @param array<string, mixed> $state
     */
    public function load(object $module, array $state): void
    {
        $predictInstances = ModuleUtils::findPredictInstances($module);

        foreach ($predictInstances as $path => $predict) {
            if (isset($state[$path]) && is_array($state[$path])) {
                /** @var list<array<string, mixed>> $demos */
                $demos = $state[$path];
                $predict->demos = $demos;
            }
        }
    }
}
