<?php
declare(strict_types=1);

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
     */
    public function __construct(array $shippingMethods, string $apiAlias)
    {
        $this->shippingMethods = $shippingMethods;
        $this->apiAlias = $apiAlias;
    }

    public function getApiAlias(): string
    {
        return $this->apiAlias;
    }
}
