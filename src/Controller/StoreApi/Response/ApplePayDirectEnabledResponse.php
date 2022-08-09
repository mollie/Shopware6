<?php

namespace Kiener\MolliePayments\Controller\StoreApi\Response;

use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct\ApplePayDirectEnabled;
use Shopware\Core\System\SalesChannel\StoreApiResponse;


class ApplePayDirectEnabledResponse extends StoreApiResponse
{

    /**
     * @var ApplePayDirectEnabled
     */
    protected $object;


    /**
     * @param ApplePayDirectEnabled $object
     */
    public function __construct(ApplePayDirectEnabled $object)
    {
        $this->object = $object;

        parent::__construct($this->object);
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->object->isEnabled();
    }

}
