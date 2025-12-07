<?php
declare(strict_types=1);

<<<<<<<< HEAD:shopware/Component/Payment/Mandate/Route/ListMandatesResponse.php
namespace Mollie\Shopware\Component\Payment\Mandate\Route;
========
namespace Mollie\Shopware\Component\Payment\Mandate;
>>>>>>>> 8c770ca6 (add terminals and refactor some classes):shopware/Component/Payment/Mandate/ListMandatesResponse.php

use Mollie\Shopware\Component\Mollie\MandateCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class ListMandatesResponse extends StoreApiResponse
{
    public function __construct(private MandateCollection $mandates)
    {
        $object = new ArrayStruct(
            [
                'mandates' => $this->mandates->jsonSerialize(),
            ],
            'mollie_payments_credit_card_mandates'
        );

        parent::__construct($object);
    }

    public function getMandates(): MandateCollection
    {
        return $this->mandates;
    }
}
