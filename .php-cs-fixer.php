<?php

declare(strict_types = 1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    // The prelude for ReadmeTest deliberately imports everything the README examples
    // may need: for the file itself those imports are "unused".
    ->exclude('tests/Fixtures')
    ->name('*.php');

return (new Config())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        // The maximum set: PSR-12 + the opinionated php-cs-fixer set + the migration to
        // 8.3, risky rules included. Below are the deliberate departures from it only.
        '@PSR12' => true,
        '@PSR12:risky' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@PHP83Migration' => true,
        '@PHP80Migration:risky' => true,

        // Classes, functions and constants are imported through use rather than written
        // with a leading "\".
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],

        // Yoda conditions: the whole project is written in the usual order, and there is
        // no point in changing that.
        'yoda_style' => false,

        // @PhpCsFixer turns /** @var */ into an ordinary comment, and PHPStan rests on it.
        'phpdoc_to_comment' => false,

        // A one-line empty body conflicts with the opening brace on a new line.
        'single_line_empty_body' => false,

        // The project's style: declare(strict_types = 1), with spaces around the "=".
        'declare_equal_normalize' => ['space' => 'single'],

        // The opening brace of functions and classes goes on a new line.
        'braces_position' => [
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
        ],

        // Spaces around a concatenation.
        'concat_space' => ['spacing' => 'one'],

        // Aligning PHPDoc into columns only gets in the way with long generic types.
        'phpdoc_align' => false,

        // The order of a class's members: constants, properties, the constructor, the
        // public methods, the private ones.
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant_public',
                'constant_protected',
                'constant_private',
                'case',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
            'sort_algorithm' => 'none',
        ],

        // A trailing comma in multiline lists, wherever that is admissible.
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters', 'match'],
        ],

        // The fixture files return a value, and need no namespace of their own.
        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,

        // assertSame compares objects by identity. The expected result of the tests is
        // decoded JSON, which can only be compared by value, so assertEquals here is a
        // deliberate choice rather than an oversight.
        'php_unit_strict' => false,
    ]);
