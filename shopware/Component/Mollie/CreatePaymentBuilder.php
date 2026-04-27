<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Transaction\PaymentTransactionStruct;

final class CreatePaymentBuilder implements CreatePaymentBuilderInterface
{
    public function __construct(private RouteBuilderInterface $routeBuilder)
    {
    }

    public function build(PaymentTransactionStruct $transaction): CreatePayment
    {
        $order = $transaction->getOrder();
        $description = $order->getOrderNumber();
        $currency = $order->getCurrency();
        $returnUrl = $this->routeBuilder->getReturnUrl($transaction->getOrderTransactionId());
        $webhookUrl = $this->routeBuilder->getWebhookUrl($transaction->getOrderTransactionId());

        $lineItemCollection = new LineItemCollection();
        $oderLineItems = $order->getLineItems();
        if ($oderLineItems !== null) {
            foreach ($oderLineItems as $lineItem) {
                $lineItem = LineItem::fromOrderLine($lineItem, $currency);
                $lineItemCollection->add($lineItem);
            }
        }

        $shippingAddress = Address::fromAddress($order->getOrderCustomer(), $order->getDeliveries()->first()->getShippingOrderAddress());

        foreach ($order->getDeliveries() as $delivery) {
            if (method_exists($order, 'getPrimaryOrderDeliveryId') && $order->getPrimaryOrderDeliveryId() !== null && $delivery->getId() === $order->getPrimaryOrderDeliveryId()) {
                $shippingAddress = Address::fromAddress($order->getOrderCustomer(), $delivery->getShippingOrderAddress());
            }

            if ($delivery->getShippingCosts()->getTotalPrice() <= 0) {
                continue;
            }

            $lineItem = LineItem::fromDelivery($delivery, $currency);
            $lineItemCollection->add($lineItem);
        }

        $billingAddress = Address::fromAddress($order->getOrderCustomer(), $order->getBillingAddress());

        $payment = new CreatePayment($description, $returnUrl, Money::fromOrder($order));
        $payment->setBillingAddress($billingAddress);
        $payment->setShippingAddress($shippingAddress);
        $payment->setLines($lineItemCollection);
        $payment->setLocale(Locale::fromLanguage($order->getLanguage()));
        $payment->setWebhookUrl($webhookUrl);

        return $payment;
    }
}
