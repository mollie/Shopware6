<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Mandate\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class RevokeMandateResponse extends StoreApiResponse
{
    public function __construct(private bool $success)
    {
        $object = new ArrayStruct(
            ['success' => $this->success],
            'mollie_payments_mandate_revoke'
        );

        parent::__construct($object);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}
