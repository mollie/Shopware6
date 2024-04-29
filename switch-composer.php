<?php

$env = (count($argv) >= 2) ? (string)$argv[1] : '';


$composerContent = file_get_contents(__DIR__ . '/composer.json');
$composerContent = json_decode($composerContent, true);


// >= 6.4.0.0 
const SW_VERSIONS_RELEASE = '6.4.1.0 - 6.7.0.0';
const SW_VERSIONS_DEV = '*';


if ($env === 'prod') {
    $composerContent = moveToProd($composerContent, SW_VERSIONS_RELEASE);
} else {
    $composerContent = moveToDev($composerContent, SW_VERSIONS_DEV);
}

file_put_contents(__DIR__ . '/composer.json', json_encode($composerContent, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));


/**
 * @param array $composerContent
 * @param string $swVersion
 * @return array
 */
function moveToDev(array $composerContent, string $swVersion)
{
    $composerContent['require-dev']["shopware/core"] = $swVersion;
    $composerContent['require-dev']["shopware/administration"] = $swVersion;
    $composerContent['require-dev']["shopware/storefront"] = $swVersion;

    unset($composerContent['require']["shopware/core"]);
    unset($composerContent['require']["shopware/administration"]);
    unset($composerContent['require']["shopware/storefront"]);

    return $composerContent;
}

/**
 * @param array $composerContent
 * @param string $swVersion
 * @return array
 */
function moveToProd(array $composerContent, string $swVersion)
{
    $composerContent['require']["shopware/core"] = $swVersion;
    $composerContent['require']["shopware/administration"] = $swVersion;
    $composerContent['require']["shopware/storefront"] = $swVersion;

    unset($composerContent['require-dev']["shopware/core"]);
    unset($composerContent['require-dev']["shopware/administration"]);
    unset($composerContent['require-dev']["shopware/storefront"]);

    return $composerContent;
}
