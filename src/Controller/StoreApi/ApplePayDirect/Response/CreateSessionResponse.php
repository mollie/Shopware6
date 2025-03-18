<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response;

use Kiener\MolliePayments\Struct\StringStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class CreateSessionResponse extends StoreApiResponse
{
    /**
     * @var StringStruct
     */
    protected $object;

    public function __construct(string $session)
    {
        $this->object = new StringStruct(
            $session,
            'mollie_payments_applepay_direct_session'
        );

        parent::__construct($this->object);
    }
}
