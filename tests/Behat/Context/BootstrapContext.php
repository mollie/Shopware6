<?php

namespace Mollie\Behat;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeSuite;

final class BootstrapContext implements Context
{
    /**
     * @BeforeSuite
     */
    public static function bootstrap(): void
    {
        require_once __DIR__ . '/../../bootstrap.php'; // or just inline your bootstrapping here, depending what you need
    }

}