<?php

namespace Kiener\MolliePayments\Controller\StoreApi\CreditCard;

use Exception;
use Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response\CreditCardMandatesResponse;
use Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response\RevokeMandateResponse;
use Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response\StoreCardTokenResponse;
use Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response\StoreMandateIdResponse;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MandateService;
use Kiener\MolliePayments\Service\MandateServiceInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\Routing\Annotation\Route;

class CreditCardControllerBase
{
    /**
     * @var CustomerService
     */
    private $customerService;

    /**
     * @var MandateServiceInterface
     */
    private $mandateService;


    /**
     * @param CustomerService $customerService
     * @param MandateServiceInterface $mandateService
     */
    public function __construct(CustomerService $customerService, MandateServiceInterface $mandateService)
    {
        $this->customerService = $customerService;
        $this->mandateService = $mandateService;
    }


    /**
     * @Route("/store-api/mollie/creditcard/store-token/{customerId}/{cardToken}", name="store-api.mollie.creditcard.store-token", methods={"POST"})
     *
     * @param string $customerId
     * @param string $cardToken
     * @param SalesChannelContext $context
     * @throws Exception
     * @return StoreApiResponse
     */
    public function saveCardToken(string $customerId, string $cardToken, RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if (!$customer instanceof CustomerEntity) {
            throw new Exception('Customer with ID ' . $customerId . ' not found in Shopware');
        }

        $result = $this->customerService->setCardToken(
            $customer,
            $cardToken,
            $context,
            $data->getBoolean('shouldSaveCardDetail', false)
        );

        return new StoreCardTokenResponse($result !== null);
    }

    /**
     * @Route("/store-api/mollie/creditcard/store-mandate-id/{customerId}/{mandateId}", name="store-api.mollie.creditcard.store-mandate-id", methods={"POST"})
     *
     * @param string $customerId
     * @param string $mandateId
     * @param SalesChannelContext $context
     * @throws Exception
     * @return StoreApiResponse
     */
    public function saveMandateId(string $customerId, string $mandateId, SalesChannelContext $context): StoreApiResponse
    {
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());
        if (!$customer instanceof CustomerEntity) {
            throw new Exception('Customer with ID ' . $customerId . ' not found in Shopware');
        }

        $result = $this->customerService->setMandateId(
            $customer,
            $mandateId,
            $context->getContext()
        );

        return new StoreMandateIdResponse($result !== null);
    }

    /**
     * @Route("/store-api/mollie/mandate/revoke/{customerId}/{mandateId}", name="store-api.mollie.mandate.revoke", methods={"POST"})
     *
     * @param string $customerId
     * @param string $mandateId
     * @param SalesChannelContext $context
     * @throws Exception
     * @return StoreApiResponse
     */
    public function revokeMandate(string $customerId, string $mandateId, SalesChannelContext $context): StoreApiResponse
    {
        $this->mandateService->revokeMandateByCustomerId($customerId, $mandateId, $context);

        return new RevokeMandateResponse();
    }

    /**
     * @Route("/store-api/mollie/mandates/{customerId}", name="store-api.mollie.mandates", methods={"GET"})
     *
     * @param string $customerId
     * @param SalesChannelContext $context
     * @throws Exception
     * @return StoreApiResponse
     */
    public function getMandates(string $customerId, SalesChannelContext $context): StoreApiResponse
    {
        $mandate = $this->mandateService->getCreditCardMandatesByCustomerId($customerId, $context);

        return new CreditCardMandatesResponse($mandate);
    }
}
