<?php

namespace Kiener\MolliePayments\Controller\StoreApi\Response;

use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct\ApplePayShipping;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class ShippingResponse extends StoreApiResponse
{

    /**
     * @var ApplePayShipping
     */
    protected $object;


    /**
     * @param ApplePayShipping $data
     */
    public function __construct(ApplePayShipping $data)
    {
        $this->object = $data;

        parent::__construct($this->object);
    }


    /**
     * @return ApplePayShipping
     */
    public function getApplePayDirectShipping(): ApplePayShipping
    {
        return $this->object;
    }


}
