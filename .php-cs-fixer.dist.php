<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->in(__DIR__)
    ->exclude([
        'dist',
        'glpi',
        'vendor',
    ])
    ->name('*.php');

return (new Config())
    ->setCacheFile(
        sys_get_temp_dir() . '/php-cs-fixer-alertsmanager.cache'
    )
    ->setRules([
        '@PER-CS3.0' => true,

        'array_syntax' => [
            'syntax' => 'short',
        ],

        'fully_qualified_strict_types' => [
            'import_symbols' => true,
        ],

        'no_unused_imports' => true,

        'ordered_imports' => [
            'imports_order' => [
                'class',
                'const',
                'function',
            ],
            'sort_algorithm' => 'alpha',
        ],

        'phpdoc_scalar' => true,
        'phpdoc_types' => true,
    ])
    ->setFinder($finder);