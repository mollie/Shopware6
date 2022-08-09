<?php

namespace Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ApplePaySession extends Struct
{


    /**
     * @var string
     */
    protected $session;


    /**
     * @param string $session
     */
    public function __construct(string $session)
    {
        $this->session = $session;
    }


    /**
     * @return string
     */
    public function getApiAlias(): string
    {
        return 'mollie_payments_routes_apple_pay_direct_session';
    }

    /**
     * @return string
     */
    public function getSession(): string
    {
        return $this->session;
    }

}