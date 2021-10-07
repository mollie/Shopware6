<?php

return PhpCsFixer\Config::create()
    ->setUsingCache(false)
    ->setRules([
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude(['Resources'])
            ->in(__DIR__ . '/src'));
