<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Mandate;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

final class MandateException extends HttpException
{
    public const NO_CUSTOMER = 'NO_CUSTOMER';
    public const MISSING_MOLLIE_CUSTOMER_ID = 'MISSING_MOLLIE_CUSTOMER_ID';
    public const ONE_CLICK_DISABLED = 'ONE_CLICK_DISABLED';

    public static function customerNotLoggedIn(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NO_CUSTOMER,
            'Customer is not logged in',
        );
    }

    public static function mollieCustomerIdNotSet(string $customerNumber): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_MOLLIE_CUSTOMER_ID,
            'Customer {customerNumber} does not have mollie customer id',
            ['customerNumber' => $customerNumber]
        );
    }

    public static function oneClickPaymentDisabled(string $salesChannelId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ONE_CLICK_DISABLED,
            'One click payment is disabled for SalesChannel {salesChannelId}',
            ['salesChannelId' => $salesChannelId]
        );
    }

    public static function customerIdNotSetForProfile(string $customerNumber,string $mollieProfileId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ONE_CLICK_DISABLED,
            'The customer {customerNumber} does not have mollie customer ID in profile {mollieProfileId}',
            ['customerNumber' => $customerNumber, 'mollieProfileId' => $mollieProfileId]
        );
    }
}
