<?php

namespace Kiener\MolliePayments\Controller\StoreApi\CreditCard;

use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\AddProductResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\FinishPaymentResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\GetCartResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\GetIDResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\GetShippingMethodsResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\IsApplePayEnabledResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\RestoreCartResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\SetShippingMethodResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\StartPaymentResponse;
use Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response\StoreCardTokenResponse;
use Kiener\MolliePayments\Controller\StoreApi\Response\CreateSessionResponse;
use Kiener\MolliePayments\Service\CustomerService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
     * @return StoreApiResponse
     * @throws \Exception
     */
    public function saveCardToken(string $customerId, string $cardToken, SalesChannelContext $context): StoreApiResponse
    {
        /** @var CustomerEntity $customer */
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
