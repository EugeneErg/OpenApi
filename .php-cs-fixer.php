<?php

declare(strict_types = 1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->name('*.php');

return (new Config())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        // Максимальный набор: PSR-12 + опинионированный набор php-cs-fixer + миграция на 8.3,
        // включая risky-правила. Ниже — только осознанные отступления от него.
        '@PSR12' => true,
        '@PSR12:risky' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@PHP83Migration' => true,
        '@PHP80Migration:risky' => true,

        // Классы, функции и константы импортируются через use, а не пишутся с ведущим «\».
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],

        // Йода-условия: весь проект написан в обычном порядке, менять его нет смысла.
        'yoda_style' => false,

        // @PhpCsFixer превращает /** @var */ в обычный комментарий, а на нём держится PHPStan.
        'phpdoc_to_comment' => false,

        // Пустое тело в одну строку конфликтует с открывающей скобкой на новой строке.
        'single_line_empty_body' => false,

        // Стиль проекта: declare(strict_types = 1) с пробелами вокруг «=».
        'declare_equal_normalize' => ['space' => 'single'],

        // Открывающая скобка функций и классов — с новой строки.
        'braces_position' => [
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
        ],

        // Пробелы вокруг конкатенации.
        'concat_space' => ['spacing' => 'one'],

        // Выравнивание PHPDoc по столбцам только мешает при длинных generic-типах.
        'phpdoc_align' => false,

        // Порядок членов класса: константы, свойства, конструктор, публичные методы, приватные.
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

        // Запятая в конце многострочных списков — везде, где допустимо.
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters', 'match'],
        ],

        // Тесты-фикстуры возвращают значение из файла, отдельный namespace им не нужен.
        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,

        // assertSame сравнивает объекты по идентичности. Ожидаемый результат тестов —
        // раскодированный JSON, его можно сравнивать только по значению, поэтому
        // assertEquals здесь осознанный выбор, а не недосмотр.
        'php_unit_strict' => false,
    ]);
