<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Exceptions;

class ApplePayDirectDomainAllowListCanNotBeEmptyException extends \Exception
{
    public function __construct()
    {
        parent::__construct('The Apple Pay Direct domain allow list can not be empty. Please check the configuration.');
    }
}
