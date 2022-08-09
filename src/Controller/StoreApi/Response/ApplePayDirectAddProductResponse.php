<?php

namespace Kiener\MolliePayments\Controller\StoreApi\Response;

use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct\AddProductStruct;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\StoreApiResponse;


class ApplePayDirectAddProductResponse extends StoreApiResponse
{
    /**
     * @var AddProductStruct
     */
    protected $object;

    /**
     * @param AddProductStruct $object
     */
    public function __construct(AddProductStruct $object)
    {
        $this->object = $object;

        parent::__construct($this->object);
    }

    /**
     * @return AddProductStruct
     */
    public function getAddProduct(): AddProductStruct
    {
        return $this->object;
    }

}
