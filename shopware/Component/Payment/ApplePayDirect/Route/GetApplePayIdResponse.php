<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

final class GetApplePayIdResponse extends StoreApiResponse
{
    public function __construct(private ?string $id)
    {
        $object = new ArrayStruct(
            [
                'success' => $this->id !== null,
                'id' => $id,
            ],
            'mollie_payments_applepay_direct_id'
        );

        parent::__construct($object);
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
