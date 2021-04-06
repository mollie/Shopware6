<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;


use Shopware\Core\Checkout\Payment\Exception\PaymentProcessException;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class PaymentUrlException extends PaymentProcessException
{


    public function getErrorCode(): string
    {
        return (string) parent::getCode();
    }
}
