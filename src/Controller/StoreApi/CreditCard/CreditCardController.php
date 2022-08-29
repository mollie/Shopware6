<?php

namespace Kiener\MolliePayments\Controller\StoreApi\CreditCard;

use Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response\StoreCardTokenResponse;
use Kiener\MolliePayments\Service\CustomerService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CreditCardController
{

    /**
     * @var CustomerService
     */
    private $customerService;


    /**
     * @param CustomerService $customerService
     */
    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }


    /**
     * @Route("/store-api/mollie/creditcard/store-token/{customerId}/{cardToken}", name="store-api.mollie.creditcard.store-token", methods={"POST"})
     *
     * @param string $customerId
     * @param string $cardToken
     * @param SalesChannelContext $context
     * @throws \Exception
     * @return StoreApiResponse
     */
    public function saveCardToken(string $customerId, string $cardToken, SalesChannelContext $context): StoreApiResponse
    {
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if (!$customer instanceof CustomerEntity) {
            throw new \Exception('Customer with ID ' . $customerId . ' not found in Shopware');
        }

        $result = $this->customerService->setCardToken(
            $customer,
            $cardToken,
            $context->getContext()
        );

        return new StoreCardTokenResponse($result !== null);
    }
}
