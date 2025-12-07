<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Route;

use Mollie\Shopware\Component\Mollie\MandateCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

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
