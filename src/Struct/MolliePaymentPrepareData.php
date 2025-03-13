<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

class MolliePaymentPrepareData
{
    /**
     * @var string
     */
    private $checkoutURL;

    /**
     * @var string
     */
    private $mollieID;

    public function __construct(string $checkoutURL, string $mollieID)
    {
        $this->checkoutURL = $checkoutURL;
        $this->mollieID = $mollieID;
    }

    public function getCheckoutURL(): string
    {
        return $this->checkoutURL;
    }

    public function getMollieID(): string
    {
        return $this->mollieID;
    }
}
