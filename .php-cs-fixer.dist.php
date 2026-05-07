<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/application',
        __DIR__ . '/library/Pt',
        __DIR__ . '/bin',
    ])
    ->append([
        __DIR__ . '/cli-bootstrap.php',
        __DIR__ . '/constants.php',
        __DIR__ . '/db-tools.php',
        __DIR__ . '/env-loader.php',
    ])
    ->name('*.php')
    ->notName('*.phtml')
    ->notName('*.phtml.bak')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'blank_line_after_namespace' => true,
        'no_extra_blank_lines' => ['tokens' => ['extra']],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
