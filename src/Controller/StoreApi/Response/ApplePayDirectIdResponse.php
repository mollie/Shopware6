<?php

namespace Kiener\MolliePayments\Controller\StoreApi\Response;

use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct\ApplePayDirectID;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;


class ApplePayDirectIdResponse extends StoreApiResponse
{

    /**
     * @var ApplePayDirectID
     */
    protected $object;


    /**
     * @param ApplePayDirectID $id
     */
    public function __construct(ApplePayDirectID $id)
    {
        $this->object = $id;

        parent::__construct($this->object);
    }


    /**
     * @return ApplePayDirectID
     */
    public function getApplePayDirectId(): ApplePayDirectID
    {
        return $this->object;
    }

}
