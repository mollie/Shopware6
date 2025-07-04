<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{success:bool, url:string, message:string, orderId: string}>>
 */
class PaymentResponse extends StoreApiResponse
{
    public function __construct(bool $success, string $redirectUrl, string $message, string $orderId)
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
}
