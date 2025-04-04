<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs\SuccessStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class RestoreCartResponse extends StoreApiResponse
{
    /**
     * @var SuccessStruct
     */
    protected $object;

    public function __construct(bool $success)
    {
        $this->object = new SuccessStruct(
            $success,
            'mollie_payments_applepay_direct_cart_restored'
        );

        parent::__construct($this->object);
    }
}
