<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class CreateSessionResponse extends StoreApiResponse
{
    /**
     * @param array<mixed> $session
     */
    public function __construct(private array $session)
    {
        $object = new ArrayStruct(
            ['session' => $session],
            'mollie_payments_applepay_direct_session'
        );

        parent::__construct($object);
    }

    /**
     * @return mixed[]
     */
    public function getSession(): array
    {
        return $this->session;
    }
}
