<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@PER-CS' => true,
        '@PER-CS:risky' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@PHPUnit100Migration:risky' => true,

        // Enforce strict types in every file
        'declare_strict_types' => true,

        // Strict comparison and params
        'strict_comparison' => true,
        'strict_param' => true,

        // Trailing comma in multiline
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments', 'arrays', 'match', 'parameters'],
        ],

        // PHPDoc
        'phpdoc_order' => true,
        'phpdoc_separation' => true,
        'phpdoc_align' => ['align' => 'left'],

        // Imports
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],

        // Native function/constant invocation for performance
        'native_function_invocation' => [
            'include' => ['@all'],
            'scope' => 'namespaced',
            'strict' => true,
        ],

        // PHPUnit - don't require @covers or @internal
        'php_unit_test_class_requires_covers' => false,
        'php_unit_internal_class' => false,
    ])
    ->setFinder($finder)
;
