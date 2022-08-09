<?php

namespace Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ApplePayDirectID extends Struct
{

    /**
     * @var string
     */
    protected $id;


    /**
     * @param string $id
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }


    /**
     * @return string
     */
    public function getApiAlias(): string
    {
        return 'mollie_payments_routes_apple_pay_direct_id';
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }


}
