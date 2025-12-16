<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class CancelCheckoutResponse extends StoreApiResponse
{
    public function __construct(private string $sessionId)
    {
        parent::__construct(new ArrayStruct(
            [
                'sessionId' => $this->sessionId,
            ],
            'paypal_express_cancel_checkout_response'
        ));
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }
}
