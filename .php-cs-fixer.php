<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->name('*.php')
    ->notName('*.blade.php');

$config = new Config();

return $config
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'cast_spaces' => ['space' => 'single'], // (string) $var - пробел между ними
        'concat_space' => ['spacing' => 'one'], // 'tgrhrt' . 'rgttrght' - пробел между объединяемыми строками
        'array_syntax' => ['syntax' => 'short'], // Использование короткого синтаксиса массивов
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => [
                '=>' => 'single_space', // Пробел вокруг =>
                '=' => 'single_space', // Пробел вокруг =
            ],
        ],
        'blank_line_after_namespace' => true, // Пустая строка после namespace
        'blank_line_after_opening_tag' => true, // Пустая строка после открывающего тега
        'blank_line_before_statement' => ['statements' => ['return']], // Пустая строка перед return
        'braces' => ['position_after_functions_and_oop_constructs' => 'next'], // Фигурные скобки на новой строке для функций и классов
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one'
            ]
        ], // Разделение методов пустыми строками
        'declare_equal_normalize' => ['space' => 'single'], // Пробелы вокруг declare
        'function_declaration' => ['closure_function_spacing' => 'one'], // Пробел после объявления функции
        'include' => true, // Пробелы вокруг include/require
        'lowercase_cast' => true, // Приведение типов в нижний регистр
        'no_empty_statement' => true, // Удаление пустых операторов
        'no_leading_import_slash' => true, // Удаление ведущего слэша в import
        'no_trailing_comma_in_list_call' => false, // Удаление запятых в вызовах функций
        'no_unused_imports' => true, // Удаление неиспользуемых import
        'no_whitespace_in_blank_line' => true, // Удаление пробелов в пустых строках
        'ordered_imports' => ['sort_algorithm' => 'alpha'], // Сортировка import по алфавиту
        'phpdoc_align' => false, // Отключение выравнивания PHPDoc
        'phpdoc_indent' => true, // Индентация PHPDoc
        'phpdoc_scalar' => true, // Приведение типов в PHPDoc в скалярный вид
        'phpdoc_separation' => true, // Разделение PHPDoc на блоки
        'phpdoc_trim' => true, // Удаление лишних пробелов в PHPDoc
        'single_trait_insert_per_statement' => true, // Один trait на строку
        'ternary_operator_spaces' => true, // Пробелы вокруг тернарного оператора
        'trim_array_spaces' => true, // Удаление пробелов в пустых массивах
        'unary_operator_spaces' => true, // Пробелы после унарных операторов
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments']], // Запятая в конце многострочных массивов и вызовов функций
        'static_lambda' => true, // Делает лямбда-функции статическими, если возможно
        'declare_strict_types' => true, // Добавление declare(strict_types=1)
        'global_namespace_import' => [
            'import_constants' => true,
            'import_functions' => true,
            'import_classes' => true,
        ],
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
        'function_typehint_space' => true, // Пробел между типом и именем аргумента
        'no_multiline_whitespace_around_double_arrow' => true, // Удаление пробелов вокруг =>
        'method_argument_space' => [
            'after_heredoc' => false,
            'keep_multiple_spaces_after_comma' => false, // Удаление множественных пробелов после запятой
        ],
    ]);
