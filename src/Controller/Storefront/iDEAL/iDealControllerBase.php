<?php

namespace Kiener\MolliePayments\Controller\Storefront\iDEAL;

use Kiener\MolliePayments\Controller\Storefront\AbstractStoreFrontController;
use Kiener\MolliePayments\Handler\Method\iDealPayment;
use Kiener\MolliePayments\Service\CustomerService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;

class iDealControllerBase extends AbstractStoreFrontController
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

        # if we have a "reset" value, then empty our stored issuer
        if ($issuerId === iDealPayment::ISSUER_RESET_VALUE) {
            $issuerId = '';
        }

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
