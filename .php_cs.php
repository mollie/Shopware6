<?php

$finder = \PhpCsFixer\Finder::create()->in([
    __DIR__ . '/src',
]);

$finder->exclude(
    [
        'Resources'
    ]
);

$config = new \PhpCsFixer\Config();

$config->setRules(
    [
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
        'align_multiline_comment' => true,
        'array_indentation' => true,
        'no_superfluous_elseif' => true,
        'not_operator_with_successor_space' => false,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_order' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_types_order' => true,
        'yoda_style' => false,
        'no_unused_imports' => true,
    ]
);

$config->setFinder($finder);

$config->setUsingCache(false);

return $config;
