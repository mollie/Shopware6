<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs;

use Shopware\Core\Framework\Struct\Struct;

class ShippingMethodsStruct extends Struct
{
    /**
     * @var array<mixed>
     */
    protected $shippingMethods;

    /**
     * @var string
     */
    private $apiAlias;


    /**
     * @param array<mixed> $shippingMethods
     * @param string $apiAlias
     */
    public function __construct(array $shippingMethods, string $apiAlias)
    {
        $this->shippingMethods = $shippingMethods;
        $this->apiAlias = $apiAlias;
    }

    /**
     * @return string
     */
    public function getApiAlias(): string
    {
        return $this->apiAlias;
    }
}
