<?php

declare(strict_types=1);

return [
    'preset' => 'symfony',
    'ide' => 'phpstorm',
    'exclude' => [
        './.git',
        './github',
        './vendor',
    ],
    'add' => [
    ],
    'remove' => [
    ],
    'config' => [
    ],
    'requirements' => [
        'min-quality' => 62,
        'min-complexity' => 22,
        'min-architecture' => 60,
        'min-style' => 70,
        'disable-security-check' => false,
    ],

];
