<?php

namespace Mollie\Behat;

use Behat\Behat\Context\Context;

final class BootstrapContext implements Context
{
    /**
     * @BeforeSuite
     */
    public static function bootstrap()
    {
        require_once __DIR__.'/../../bootstrap.php'; // or just inline your bootstrapping here, depending what you need
    }

}