<?php

namespace Kiener\MolliePayments\Controller\Storefront\CreditCard;

use Exception;
use Kiener\MolliePayments\Service\CustomerServiceInterface;
use Kiener\MolliePayments\Service\MandateServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CreditCardControllerBase extends StorefrontController
{
    /**
     * @var CustomerServiceInterface
     */
    private $customerService;

    /**
     * @var MandateServiceInterface
     */
    private $mandateService;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param CustomerServiceInterface $customerService
     * @param MandateServiceInterface $mandateService
     * @param LoggerInterface $logger
     */
    public function __construct(CustomerServiceInterface $customerService, MandateServiceInterface $mandateService, LoggerInterface $logger)
    {
        $this->customerService = $customerService;
        $this->mandateService = $mandateService;
        $this->logger = $logger;
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
    public function storeCardToken(SalesChannelContext $context, string $customerId, string $cardToken, Request $data): JsonResponse
    {
        $result = null;
        $success = false;
        /** @var CustomerEntity $customer */
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if ($customer instanceof CustomerEntity) {
            $writtenEvent = $this->customerService->setCardToken(
                $customer,
                $cardToken,
                $context,
                $data->query->getBoolean('shouldSaveCardDetail', false)
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

    /**
     * @Route("/mollie/components/store-mandate-id/{customerId}/{mandateId}", name="frontend.mollie.components.storeMandateId", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @param string $customerId
     * @param string $mandateId
     *
     * @return JsonResponse
     */
    public function storeMandateId(string $customerId, string $mandateId, SalesChannelContext $context): JsonResponse
    {
        $result = null;
        $success = false;
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());
        if ($customer instanceof CustomerEntity) {
            $writtenEvent = $this->customerService->setMandateId(
                $customer,
                $mandateId,
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

    /**
     * @Route("/mollie/components/revoke-mandate/{customerId}/{mandateId}", name="frontend.mollie.components.revokeMandate", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @param string $customerId
     * @param string $mandateId
     *
     * @return JsonResponse
     */
    public function revokeMandate(string $customerId, string $mandateId, SalesChannelContext $context): JsonResponse
    {
        $success = false;
        $result = null;

        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if ($customer instanceof CustomerEntity) {
            try {
                $this->mandateService->revokeMandateByCustomerId($customerId, $mandateId, $context);

                $this->logger->info('One-Click Payments customer ' . $customerId . ' removed stored mandate ' . $mandateId);

                $success = true;
            } catch (Exception $exception) {
                $this->logger->error(
                    'One-Click Payments  error when removing mandate from customer',
                    [
                        'error' => $exception
                    ]
                );

                $result = 'Error when removing mandate';
            }
        }

        return new JsonResponse(
            [
                'success' => $success,
                'customerId' => $customerId,
                'mandateId' => $mandateId,
                'result' => $result
            ]
        );
    }
}
