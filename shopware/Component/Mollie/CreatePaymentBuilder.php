<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CreatePaymentBuilder implements CreatePaymentBuilderInterface
{
    public function __construct(
        #[Autowire(service: RouteBuilder::class)]
        private RouteBuilderInterface $routeBuilder,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
    ) {
    }

    public function build(TransactionDataStruct $transactionData): CreatePayment
    {
        $transactionId = $transactionData->getTransaction()->getId();
        $order = $transactionData->getOrder();
        $customer = $transactionData->getCustomer();
        $currency = $transactionData->getCurrency();
        $language = $transactionData->getLanguage();
        $shippingOrderAddress = $transactionData->getShippingOrderAddress();
        $billingOrderAddress = $transactionData->getBillingOrderAddress();
        $deliveries = $transactionData->getDeliveries();

        $paymentSettings = $this->settingsService->getPaymentSettings($order->getSalesChannelId());
        $orderNumberFormat = $paymentSettings->getOrderNumberFormat();

        $customerNumber = $customer->getCustomerNumber();
        $description = (string) $order->getOrderNumber();
        $orderNumber = (string) $order->getOrderNumber();

        if (mb_strlen($orderNumberFormat) > 0) {
            $description = str_replace([
                '{ordernumber}',
                '{customernumber}'
            ], [
                $orderNumber,
                $customerNumber
            ], $orderNumberFormat);
        }

        $returnUrl = $this->routeBuilder->getReturnUrl($transactionId);
        $webhookUrl = $this->routeBuilder->getWebhookUrl($transactionId);

        $lineItemCollection = new LineItemCollection();
        $oderLineItems = $order->getLineItems();
        if ($oderLineItems !== null) {
            foreach ($oderLineItems as $lineItem) {
                $lineItem = LineItem::fromOrderLine($lineItem, $currency);
                $lineItemCollection->add($lineItem);
            }
        }

        $shippingAddress = Address::fromAddress($customer, $shippingOrderAddress);

        foreach ($deliveries as $delivery) {
            $deliveryOrderShippingAddress = $delivery->getShippingOrderAddress();
            if (method_exists($order, 'getPrimaryOrderDeliveryId')
                && $deliveryOrderShippingAddress instanceof OrderAddressEntity
                && $order->getPrimaryOrderDeliveryId() !== null
                && $delivery->getId() === $order->getPrimaryOrderDeliveryId()
            ) {
                $shippingAddress = Address::fromAddress($customer, $deliveryOrderShippingAddress);
            }

            if ($delivery->getShippingCosts()->getTotalPrice() <= 0) {
                continue;
            }

            $lineItem = LineItem::fromDelivery($delivery, $currency);
            $lineItemCollection->add($lineItem);
        }

        $billingAddress = Address::fromAddress($customer, $billingOrderAddress);

        $payment = new CreatePayment($description, $returnUrl, Money::fromOrder($order, $currency));
        $payment->setBillingAddress($billingAddress);
        $payment->setShippingAddress($shippingAddress);
        $payment->setLines($lineItemCollection);
        $payment->setLocale(Locale::fromLanguage($language));
        $payment->setWebhookUrl($webhookUrl);
        $payment->setShopwareOrderNumber($orderNumber);

        return $payment;
    }
}
