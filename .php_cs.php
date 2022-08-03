<?php

return PhpCsFixer\Config::create()
    ->setUsingCache(false)
    ->setRules([
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
        'yoda_style' => null,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude(['Resources'])
            ->in(__DIR__ . '/src'));
