<?php

namespace Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response;

use Kiener\MolliePayments\Struct\Mandate\MandateCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class CreditCardMandatesResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<mixed, mixed>
     */
    protected $object;


    /**
     * @param MandateCollection $mandates
     */
    public function __construct(MandateCollection $mandates)
    {
        $this->object = new ArrayStruct(
            [
                'mandates' => $mandates->jsonSerialize(),
            ],
            'mollie_payments_credit_card_mandates'
        );

        parent::__construct($this->object);
    }
}
