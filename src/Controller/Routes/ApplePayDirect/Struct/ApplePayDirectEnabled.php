<?php

namespace Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ApplePayDirectEnabled extends Struct
{
    /**
     * @var bool
     */
    protected $enabled;

    /**
     * @param bool $enabled
     */
    public function __construct(bool $enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * @return string
     */
    public function getApiAlias(): string
    {
        return 'mollie_payments_routes_apple_pay_direct_enabled';
    }


    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

}
