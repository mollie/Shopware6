<?php

namespace Kiener\MolliePayments\Service\MollieApi\RequestAnonymizer;

class MollieRequestAnonymizer
{
    /**
     * @var string
     */
    private $placeholder;


    /**
     * @param string $placeholder
     */
    public function __construct(string $placeholder)
    {
        $this->placeholder = $placeholder;
    }


    /**
     * Anonymizes the Mollie Orders API request data
     * https://docs.mollie.com/reference/v2/orders-api/create-order
     *
     * @param array<mixed> $requestData
     * @return array<mixed>
     */
    public function anonymize(array $requestData)
    {
        if (empty($requestData)) {
            return $requestData;
        }

        if (isset($requestData['billingAddress'])) {
            $requestData['billingAddress']['organizationName'] = $this->placeholder;
            $requestData['billingAddress']['streetAndNumber'] = $this->placeholder;
            $requestData['billingAddress']['givenName'] = $this->placeholder;
            $requestData['billingAddress']['familyName'] = $this->placeholder;
            $requestData['billingAddress']['email'] = $this->placeholder;
            $requestData['billingAddress']['phone'] = $this->placeholder;
        }

        if (isset($requestData['shippingAddress'])) {
            $requestData['shippingAddress']['organizationName'] = $this->placeholder;
            $requestData['shippingAddress']['streetAndNumber'] = $this->placeholder;
            $requestData['shippingAddress']['givenName'] = $this->placeholder;
            $requestData['shippingAddress']['familyName'] = $this->placeholder;
            $requestData['shippingAddress']['email'] = $this->placeholder;
            $requestData['shippingAddress']['phone'] = $this->placeholder;
        }

        return $requestData;
    }
}
