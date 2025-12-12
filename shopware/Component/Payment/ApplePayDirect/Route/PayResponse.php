<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class PayResponse extends StoreApiResponse
{
    public function __construct(bool $success, private string $redirectUrl,string $message,private string $orderId,private SalesChannelContext $salesChannelContext)
    {
        $object = new ArrayStruct(
            [
                'success' => $success,
                'url' => $redirectUrl,
                'message' => $message,
                'orderId' => $orderId,
            ],
            'mollie_payments_applepay_direct_payment'
        );

        parent::__construct($object);
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
