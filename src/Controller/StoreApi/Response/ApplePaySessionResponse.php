<?php

namespace Kiener\MolliePayments\Controller\StoreApi\Response;

use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct\ApplePaySession;
use Shopware\Core\System\SalesChannel\StoreApiResponse;


class ApplePaySessionResponse extends StoreApiResponse
{

    /**
     * @var ApplePaySession
     */
    protected $object;


    /**
     * @param ApplePaySession $session
     */
    public function __construct(ApplePaySession $session)
    {
        $this->object = $session;

        parent::__construct($this->object);
    }


    /**
     * @return ApplePaySession
     */
    public function getApplePayDirectSession(): ApplePaySession
    {
        return $this->object;
    }

}
