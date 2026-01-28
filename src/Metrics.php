<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

use Closure;
use ReflectionProperty;

use function array_count_values;
use function array_filter;
use function count;
use function explode;
use function min;
use function strtolower;

final class Metrics
{
    /**
     * Exact string match (case-insensitive) on a field.
     *
     * @return Closure(object, object): bool
     */
    public static function exactMatch(string $field = 'answer'): Closure
    {
        return static function (object $example, object $prediction) use ($field): bool {
            $expected = (new ReflectionProperty($example, $field))->getValue($example);
            $actual = (new ReflectionProperty($prediction, $field))->getValue($prediction);

            return strtolower((string) $expected) === strtolower((string) $actual); // @phpstan-ignore cast.string, cast.string
        };
    }

    /**
     * F1 token overlap on a field.
     *
     * @return Closure(object, object): float
     */
    public static function f1(string $field = 'answer'): Closure
    {
        return static function (object $example, object $prediction) use ($field): float {
            $expected = (string) (new ReflectionProperty($example, $field))->getValue($example); // @phpstan-ignore cast.string
            $actual = (string) (new ReflectionProperty($prediction, $field))->getValue($prediction); // @phpstan-ignore cast.string

            $expectedTokens = array_filter(explode(' ', strtolower($expected)), static fn (string $t): bool => '' !== $t);
            $actualTokens = array_filter(explode(' ', strtolower($actual)), static fn (string $t): bool => '' !== $t);

            if (0 === count($expectedTokens) || 0 === count($actualTokens)) {
                return 0.0;
            }

            $expectedCounts = array_count_values($expectedTokens);
            $actualCounts = array_count_values($actualTokens);
            $numCommon = 0;
            foreach ($actualCounts as $token => $actualCount) {
                if (isset($expectedCounts[$token])) {
                    $numCommon += min($actualCount, $expectedCounts[$token]);
                }
            }

            if (0 === $numCommon) {
                return 0.0;
            }

            $precision = $numCommon / count($actualTokens);
            $recall = $numCommon / count($expectedTokens);

            return 2.0 * ($precision * $recall) / ($precision + $recall);
        };
    }
}
