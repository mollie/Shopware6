<?php

namespace Kiener\MolliePayments\Controller\Storefront\iDEAL;

use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Router;

class iDealControllerBase extends StorefrontController
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
     * @Route("/mollie/ideal/store-issuer/{customerId}/{issuerId}", name="frontend.mollie.ideal.storeIssuer", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @param string $customerId
     * @param string $issuerId
     *
     * @return JsonResponse
     */
    public function storeIssuer(SalesChannelContext $context, string $customerId, string $issuerId): JsonResponse
    {
        $result = null;

        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if ($customer instanceof CustomerEntity) {
            $writtenEvent = $this->customerService->setIDealIssuer(
                $customer,
                $issuerId,
                $context->getContext()
            );

            $result = $writtenEvent->getErrors();
        }

        return new JsonResponse([
            'success' => (bool)$result,
            'customerId' => $customerId,
            'result' => $result,
        ]);
    }
}
