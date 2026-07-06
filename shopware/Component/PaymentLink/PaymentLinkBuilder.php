<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\PaymentLink;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Payment\LineCollectionBuilder;
use Mollie\Shopware\Component\Payment\LineCollectionBuilderInterface;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaymentLinkBuilder implements PaymentLinkBuilderInterface
{
    public function __construct(
        #[Autowire(service: RouteBuilder::class)]
        private readonly RouteBuilderInterface $routeBuilder,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: LineCollectionBuilder::class)]
        private readonly LineCollectionBuilderInterface $lineCollectionBuilder,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function build(TransactionDataStruct $transactionData): CreatePaymentLink
    {
        $transactionId = $transactionData->getTransaction()->getId();
        $order = $transactionData->getOrder();
        $salesChannelId = $order->getSalesChannelId();
        $customer = $transactionData->getCustomer();
        $currency = $transactionData->getCurrency();
        $orderNumber = (string) $order->getOrderNumber();
        $taxStatus = (string) $order->getTaxStatus();

        $amount = Money::fromOrder($order, $currency);

        $billingOrderAddress = $transactionData->getBillingOrderAddress();
        $billingAddress = Address::fromAddress($customer, $billingOrderAddress);
        $shippingAddress = Address::fromAddress($customer, $transactionData->getShippingOrderAddress());

        $lines = $this->lineCollectionBuilder->build($order, $transactionData->getDeliveries(), $currency, $taxStatus);

        $billingCountry = (string) ($billingOrderAddress->getCountry()?->getIso() ?? '');
        $allowedMethods = $this->mollieGateway->getActivePaymentMethods($amount, $billingCountry, $salesChannelId);

        $createPaymentLink = new CreatePaymentLink($orderNumber, $amount);
        $createPaymentLink->setBillingAddress($billingAddress);
        $createPaymentLink->setShippingAddress($shippingAddress);
        $createPaymentLink->setLines($lines);
        $createPaymentLink->setAllowedMethods($allowedMethods);
        $createPaymentLink->setRedirectUrl($this->routeBuilder->getReturnUrl($transactionId));
        $createPaymentLink->setWebhookUrl($this->routeBuilder->getWebhookUrl($transactionId));

        $this->logger->info('Payment link payload created for mollie API', [
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
            'allowedMethods' => $allowedMethods,
        ]);

        return $createPaymentLink;
    }
}
