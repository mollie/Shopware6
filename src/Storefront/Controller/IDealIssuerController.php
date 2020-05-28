<?php

namespace Kiener\MolliePayments\Storefront\Controller;

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

class IDealIssuerController extends StorefrontController
{
    /** @var CustomerService */
    private $customerService;

    public function __construct(
        CustomerService $customerService
    )
    {
        $this->customerService = $customerService;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/ideal/store-issuer/{customerId}/{issuerId}", name="frontend.mollie.ideal.storeIssuer", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @param string              $customerId
     * @param string              $issuerId
     *
     * @return JsonResponse
     */
    public function storeIDealIssuer(SalesChannelContext $context, string $customerId, string $issuerId): JsonResponse
    {
        $result = null;

        /** @var CustomerEntity $customer */
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if ($customer !== null) {
            $result = $this->customerService->setIDealIssuer(
                $customer,
                $issuerId,
                $context->getContext()
            );
        }

        /**
         * Output the json result.
         */
        return new JsonResponse([
            'success' => (bool) $result,
            'customerId' => $customerId,
            'result' => $result->getErrors()
        ]);
    }
}