<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs;

use Shopware\Core\Framework\Struct\Struct;

class ApplePayCartStruct extends Struct
{
    /**
     * @var array<mixed>
     */
    protected $cart;

    /**
     * @var string
     */
    private $apiAlias;


    /**
     * @param mixed[] $cart
     * @param string $apiAlias
     */
    public function __construct(array $cart, string $apiAlias)
    {
        $this->cart = $cart;
        $this->apiAlias = $apiAlias;
    }


    /**
     * @return string
     */
    public function getApiAlias(): string
    {
        return $this->apiAlias;
    }


    /**
     * @return array<mixed>
     */
    public function getApplePayCart(): array
    {
        return $this->cart;
    }
}
