<?php

namespace Kiener\MolliePayments\Controller\StoreApi\iDEAL;

use Kiener\MolliePayments\Controller\StoreApi\iDEAL\Response\IssuersResponse;
use Kiener\MolliePayments\Controller\StoreApi\iDEAL\Response\StoreIssuerResponse;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Handler\Method\iDealPayment;
use Kiener\MolliePayments\Service\CustomerService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\Routing\Annotation\Route;

class iDealControllerBase
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

        $issuers = $this->mollieGateway->getIDealIssuers();

        $issuerArray = [];

        foreach ($issuers as $issuer) {
            $issuerArray[] = [
                'id' => $issuer->getId(),
                'name' => $issuer->getName(),
                'images' => [
                    '1x' => $issuer->getImage1x(),
                    '2x' => $issuer->getImage2x(),
                    'svg' => $issuer->getSvg(),
                ],
            ];
        }

        return new IssuersResponse($issuerArray);
    }

    /**
     *
     * @param string $customerId
     * @param string $issuerId
     * @param SalesChannelContext $context
     * @throws \Exception
     * @return StoreApiResponse
     */
    public function saveIssuer(string $customerId, string $issuerId, SalesChannelContext $context): StoreApiResponse
    {
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if (!$customer instanceof CustomerEntity) {
            throw new \Exception('Customer with ID ' . $customerId . ' not found in Shopware');
        }

        # if we have a "reset" value, then empty our stored issuer
        if ($issuerId === iDealPayment::ISSUER_RESET_VALUE) {
            $issuerId = '';
        }

        $result = $this->customerService->setIDealIssuer(
            $customer,
            $issuerId,
            $context->getContext()
        );

        return new StoreIssuerResponse($result !== null);
    }
}
