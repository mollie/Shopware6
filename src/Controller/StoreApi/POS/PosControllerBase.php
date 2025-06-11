<?php
declare(strict_types=1);

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

    public function __construct(CustomerService $customerService, MollieGatewayInterface $mollieGateway)
    {
        $this->customerService = $customerService;
        $this->mollieGateway = $mollieGateway;
    }

    /**
     * @return TerminalsResponse
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
     * @throws \Exception
     *
     * @return StoreTerminalResponse
     */
    public function saveTerminalId(string $customerId, string $terminalID, SalesChannelContext $context): StoreApiResponse
    {
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if (! $customer instanceof CustomerEntity) {
            throw new \Exception('Customer with ID ' . $customerId . ' not found in Shopware');
        }

        $result = $this->customerService->setPosTerminal(
            $customer,
            $terminalID,
            $context->getContext()
        );
        $success = count($result->getErrors()) === 0;

        return new StoreTerminalResponse($success);
    }
}
