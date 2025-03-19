<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\CreditCard;

use Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response\CreditCardMandatesResponse;
use Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response\RevokeMandateResponse;
use Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response\StoreCardTokenResponse;
use Kiener\MolliePayments\Controller\StoreApi\CreditCard\Response\StoreMandateIdResponse;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MandateServiceInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

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

    public function __construct(CustomerService $customerService, MandateServiceInterface $mandateService)
    {
        $this->customerService = $customerService;
        $this->mandateService = $mandateService;
    }

    /**
     * @throws \Exception
     */
    public function saveCardToken(string $customerId, string $cardToken, RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        /** @var ?CustomerEntity $customer */
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if (! $customer instanceof CustomerEntity) {
            throw new \Exception('Customer with ID ' . $customerId . ' not found in Shopware');
        }

        $result = $this->customerService->setCardToken(
            $customer,
            $cardToken,
            $context,
            $data->getBoolean('shouldSaveCardDetail')
        );
        $success = count($result->getErrors()) === 0;

        return new StoreCardTokenResponse($success);
    }

    /**
     * @throws \Exception
     */
    public function saveMandateId(string $customerId, string $mandateId, SalesChannelContext $context): StoreApiResponse
    {
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());
        if (! $customer instanceof CustomerEntity) {
            throw new \Exception('Customer with ID ' . $customerId . ' not found in Shopware');
        }

        $result = $this->customerService->setMandateId(
            $customer,
            $mandateId,
            $context->getContext()
        );
        $success = count($result->getErrors()) === 0;

        return new StoreMandateIdResponse($success);
    }

    /**
     * @throws \Exception
     */
    public function revokeMandate(string $customerId, string $mandateId, SalesChannelContext $context): StoreApiResponse
    {
        $this->mandateService->revokeMandateByCustomerId($customerId, $mandateId, $context);

        return new RevokeMandateResponse();
    }

    /**
     * @throws \Exception
     */
    public function getMandates(string $customerId, SalesChannelContext $context): StoreApiResponse
    {
        $mandate = $this->mandateService->getCreditCardMandatesByCustomerId($customerId, $context);

        return new CreditCardMandatesResponse($mandate);
    }
}
