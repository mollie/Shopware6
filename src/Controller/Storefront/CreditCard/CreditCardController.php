<?php

namespace Kiener\MolliePayments\Controller\Storefront\CreditCard;

use Kiener\MolliePayments\Service\CustomerService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CreditCardController extends StorefrontController
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
     * @Route("/mollie/components/store-card-token/{customerId}/{cardToken}", name="frontend.mollie.components.storeCardToken", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @param string $customerId
     * @param string $cardToken
     *
     * @return JsonResponse
     */
    public function storeCardToken(SalesChannelContext $context, string $customerId, string $cardToken): JsonResponse
    {
        $result = null;

        /** @var CustomerEntity $customer */
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if ($customer instanceof CustomerEntity) {
            $writtenEvent = $this->customerService->setCardToken(
                $customer,
                $cardToken,
                $context->getContext()
            );
            $result = $writtenEvent->getErrors();
        }

        return new JsonResponse([
            'success' => (bool)$result,
            'customerId' => $customerId,
            'result' => $result
        ]);
    }
}
