<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\Mollie\Payment;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class WebhookResponse extends StoreApiResponse
{
    public function __construct(private Payment $payment)
    {
        parent::__construct(new ArrayStruct(
            [
                'payment' => $this->payment,
                'status' => 'success',
            ], 'webhook.route.success'
        ));
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }
}
