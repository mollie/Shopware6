<?php

namespace Kiener\MolliePayments\Controller\Storefront\CreditCard;

use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomerServiceInterface;
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
     * @var CustomerServiceInterface
     */
    private $customerService;


    /**
     * @param CustomerServiceInterface $customerService
     */
    public function __construct(CustomerServiceInterface $customerService)
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
        $success = false;
        /** @var CustomerEntity $customer */
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if ($customer instanceof CustomerEntity) {
            $writtenEvent = $this->customerService->setCardToken(
                $customer,
                $cardToken,
                $context->getContext()
            );
            $errors = $writtenEvent->getErrors();
            $success = count($errors) === 0;
            $result = $errors;
        }

        return new JsonResponse([
            'success' => $success,
            'customerId' => $customerId,
            'result' => $result
        ]);
    }
}
