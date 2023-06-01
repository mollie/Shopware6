<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\EnabledStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class IsApplePayEnabledResponse extends StoreApiResponse
{
    /**
     * @var EnabledStruct
     */
    protected $object;


    /**
     * @param bool $isEnabled
     */
    public function __construct(bool $isEnabled)
    {
        $this->object = new EnabledStruct(
            $isEnabled,
            'mollie_payments_applepay_direct_enabled'
        );

        parent::__construct($this->object);
    }
}
