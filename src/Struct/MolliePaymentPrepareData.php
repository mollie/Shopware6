<?php

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

    /**
     * @param string $checkoutURL
     * @param string $mollieID
     */
    public function __construct(string $checkoutURL, string $mollieID)
    {
        $this->checkoutURL = $checkoutURL;
        $this->mollieID = $mollieID;
    }

    /**
     * @return string
     */
    public function getCheckoutURL(): string
    {
        return $this->checkoutURL;
    }

    /**
     * @return string
     */
    public function getMollieID(): string
    {
        return $this->mollieID;
    }
}
