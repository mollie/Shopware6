<?php

namespace Kiener\MolliePayments\Controller\StoreApi\POS;

use Kiener\MolliePayments\Controller\StoreApi\POS\Response\StoreTerminalResponse;
use Kiener\MolliePayments\Controller\StoreApi\POS\Response\TerminalsResponse;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class PosControllerBase
{

    /**
     * @var CustomerService
     */
    private $customerService;

    /**
     * @var MollieGatewayInterface
     */
    private $mollieGateway;


    /**
     * @param CustomerService $customerService
     * @param MollieGatewayInterface $mollieGateway
     */
    public function __construct(CustomerService $customerService, MollieGatewayInterface $mollieGateway)
    {
        $this->customerService = $customerService;
        $this->mollieGateway = $mollieGateway;
    }


    /**
     *
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     */
    public function getIssuers(SalesChannelContext $context): StoreApiResponse
    {
        $this->mollieGateway->switchClient($context->getSalesChannelId());

        $terminalsArray = [];

        $terminals = $this->mollieGateway->getPosTerminals();

        foreach ($terminals as $terminal) {
            $terminalsArray[] = [
                'id' => $terminal->id,
                'name' => $terminal->description,
            ];
        }

        return new TerminalsResponse($terminalsArray);
    }

    /**
     *
     * @param string $customerId
     * @param string $terminalID
     * @param SalesChannelContext $context
     * @throws \Exception
     * @return StoreApiResponse
     */
    public function saveTerminalId(string $customerId, string $terminalID, SalesChannelContext $context): StoreApiResponse
    {
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if (!$customer instanceof CustomerEntity) {
            throw new \Exception('Customer with ID ' . $customerId . ' not found in Shopware');
        }

        $result = $this->customerService->setPosTerminal(
            $customer,
            $terminalID,
            $context->getContext()
        );

        return new StoreTerminalResponse($result !== null);
    }
}
