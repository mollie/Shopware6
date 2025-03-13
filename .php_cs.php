<?php declare(strict_types=1);

$finder = \PhpCsFixer\Finder::create()->in([
    __DIR__ . '/src',
    __DIR__ . '/shopware',
    __DIR__.'/tests/PHPUnit',
]);

$finder->exclude(
    [
        'Resources'
    ]
);

$config = new PhpCsFixer\Config();

return $config->setRules(
    [
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        '@Symfony' => true,
        '@DoctrineAnnotation' => true,
        'phpdoc_align' => [
            'align' => 'left',
        ],
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_unused_imports' => true,
        'phpdoc_no_empty_return' => false,
        'ordered_class_elements' => true,
        'not_operator_with_successor_space' => true,
        'declare_strict_types' => true,
        'ordered_imports' => true,
        'phpdoc_order' => true,
        'php_unit_test_annotation' => false,
        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,
        'phpdoc_summary' => false,
        'blank_line_after_opening_tag' => false,
        'concat_space' => ['spacing' => 'one'],
        'array_syntax' => ['syntax' => 'short'],
        'yoda_style' => false,
        'align_multiline_comment' => true,
        'array_indentation' => true,
        'no_superfluous_elseif' => true,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_types_order' => true,
    ]
)
    ->setRiskyAllowed(true)
    ->setUsingCache(false)
    ->setFinder($finder);
