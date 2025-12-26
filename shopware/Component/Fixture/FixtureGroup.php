<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture;

enum FixtureGroup: string
{
    case DATA = 'data';
    case SETUP = 'setup';
}
