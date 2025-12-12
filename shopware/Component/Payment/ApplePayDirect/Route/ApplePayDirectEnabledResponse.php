<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

final class ApplePayDirectEnabledResponse extends StoreApiResponse
{
    public function __construct(private bool $enabled,private ?string $paymentMethodId = null)
    {
        $object = new ArrayStruct(
            [
                'enabled' => $this->enabled,
                'paymentMethodId' => $this->paymentMethodId,
            ],
            'mollie_payments_applepay_direct_enabled'
        );

        parent::__construct($object);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getPaymentMethodId(): ?string
    {
        return $this->paymentMethodId;
    }
}
