<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response;

use Kiener\MolliePayments\Struct\Mandate\MandateCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{mandates:array}>>
 */
class CreditCardMandatesResponse extends StoreApiResponse
{
    public function __construct(MandateCollection $mandates)
    {
        $object = new ArrayStruct(
            [
                'mandates' => $mandates->jsonSerialize(),
            ],
            'mollie_payments_credit_card_mandates'
        );

        parent::__construct($object);
    }
}
