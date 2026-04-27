<?php
declare(strict_types=1);

<<<<<<<< HEAD:shopware/Component/Payment/Mandate/Route/StoreMandateIdResponse.php
namespace Mollie\Shopware\Component\Payment\Mandate\Route;
========
namespace Mollie\Shopware\Component\Payment\Mandate;
>>>>>>>> 8c770ca6 (add terminals and refactor some classes):shopware/Component/Payment/Mandate/StoreMandateIdResponse.php

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class StoreMandateIdResponse extends StoreApiResponse
{
    public function __construct()
    {
        $object = new ArrayStruct(
            [
                'success' => true,
                'message' => 'Using deprecated route, please provide "mandateId" in request body for payment'
            ],
            'mollie_payments_creditcard_mandate_id_stored'
        );

        parent::__construct($object);
    }
}
