<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Shopware\Core\Checkout\Order\OrderEntity;

final class CreatePaymentBuilder implements CreatePaymentBuilderInterface
{
    public function __construct(
        private RouteBuilderInterface $routeBuilder,
        private AbstractSettingsService $settingsService,
    ) {
    }

    public function build(string $transactionId, OrderEntity $order): CreatePayment
    {
        $paymentSettings = $this->settingsService->getPaymentSettings($order->getSalesChannelId());
        $orderNumberFormat = $paymentSettings->getOrderNumberFormat();
        $orderNumber = $order->getOrderNumber();
        $customer = $order->getOrderCustomer();
        $description = $order->getOrderNumber();
        $deliveries = $order->getDeliveries();

        if (mb_strlen($orderNumberFormat) > 0) {
            $description = str_replace([
                '{ordernumber}',
                '{customernumber}'
            ], [
                $orderNumber,
                $customer->getCustomerNumber()
            ], $orderNumberFormat);
        }
        $currency = $order->getCurrency();
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

        $shippingAddress = Address::fromAddress($customer, $deliveries->first()->getShippingOrderAddress());

        foreach ($deliveries as $delivery) {
            if (method_exists($order, 'getPrimaryOrderDeliveryId') && $order->getPrimaryOrderDeliveryId() !== null && $delivery->getId() === $order->getPrimaryOrderDeliveryId()) {
                $shippingAddress = Address::fromAddress($customer, $delivery->getShippingOrderAddress());
            }

            if ($delivery->getShippingCosts()->getTotalPrice() <= 0) {
                continue;
            }

            $lineItem = LineItem::fromDelivery($delivery, $currency);
            $lineItemCollection->add($lineItem);
        }

        $billingAddress = Address::fromAddress($customer, $order->getBillingAddress());

        $payment = new CreatePayment($description, $returnUrl, Money::fromOrder($order));
        $payment->setCaptureMode(new CaptureMode(CaptureMode::AUTOMATIC));
        $payment->setBillingAddress($billingAddress);
        $payment->setShippingAddress($shippingAddress);
        $payment->setLines($lineItemCollection);
        $payment->setLocale(Locale::fromLanguage($order->getLanguage()));
        $payment->setWebhookUrl($webhookUrl);

        return $payment;
    }
}
