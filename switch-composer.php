<?php

$env = (count($argv) >= 2) ? (string)$argv[1] : '';


$composerContent = file_get_contents(__DIR__ . '/composer.json');
$composerContent = json_decode($composerContent, true);


if ($env === 'prod') {
    $composerContent = moveToProd($composerContent);
} else {
    $composerContent = moveToDev($composerContent);
}

file_put_contents(__DIR__ . '/composer.json', json_encode($composerContent, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));


/**
 * @param array $composerContent
 * @return array
 */
function moveToDev(array $composerContent)
{
    $composerContent['require-dev']["shopware/core"] = '*';
    $composerContent['require-dev']["shopware/administration"] = '*';
    $composerContent['require-dev']["shopware/storefront"] = '*';

    unset($composerContent['require']["shopware/core"]);
    unset($composerContent['require']["shopware/administration"]);
    unset($composerContent['require']["shopware/storefront"]);

    return $composerContent;
}

/**
 * @param array $composerContent
 * @return array
 */
function moveToProd(array $composerContent)
{
    $composerContent['require']["shopware/core"] = '*';
    $composerContent['require']["shopware/administration"] = '*';
    $composerContent['require']["shopware/storefront"] = '*';

    unset($composerContent['require-dev']["shopware/core"]);
    unset($composerContent['require-dev']["shopware/administration"]);
    unset($composerContent['require-dev']["shopware/storefront"]);

    return $composerContent;
}
