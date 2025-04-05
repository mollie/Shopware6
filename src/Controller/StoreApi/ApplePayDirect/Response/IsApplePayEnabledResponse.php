<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\EnabledStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class IsApplePayEnabledResponse extends StoreApiResponse
{
    public function __construct(bool $isEnabled)
    {
        $object = new EnabledStruct(
            $isEnabled,
            'mollie_payments_applepay_direct_enabled'
        );

        parent::__construct($object);
    }
}
