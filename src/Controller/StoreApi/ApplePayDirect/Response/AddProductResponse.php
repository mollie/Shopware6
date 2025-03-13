<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class AddProductResponse extends StoreApiResponse
{
    /**
     * @var Cart
     */
    protected $object;

    public function __construct(Cart $object)
    {
        $this->object = $object;

        parent::__construct($this->object);
    }
}
