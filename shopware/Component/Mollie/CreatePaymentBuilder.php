<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;

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
        $orderNumber = (string) $order->getOrderNumber();
        $customer = $order->getOrderCustomer();

        if (! $customer instanceof OrderCustomerEntity) {
            throw new \Exception('Order without customer');
        }
        $customerNumber = (string) $customer->getCustomerNumber();
        $description = (string) $order->getOrderNumber();
        $deliveries = $order->getDeliveries();
        $language = $order->getLanguage();

        if (! $deliveries instanceof OrderDeliveryCollection) {
            throw new \Exception('Order without deliveries');
        }
        if (! $language instanceof LanguageEntity) {
            throw new \Exception('Order without language');
        }
        if (mb_strlen($orderNumberFormat) > 0) {
            $description = str_replace([
                '{ordernumber}',
                '{customernumber}'
            ], [
                $orderNumber,
                $customerNumber
            ], $orderNumberFormat);
        }
        $currency = $order->getCurrency();
        if (! $currency instanceof CurrencyEntity) {
            throw new \Exception('Order does not have a currency'); // TODO:
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

        $firstDeliveryLine = $deliveries->first();
        if (! $firstDeliveryLine instanceof OrderDeliveryEntity) {
            throw new \Exception('Order does not have a delivery line');
        }
        $shippingOrderAddress = $firstDeliveryLine->getShippingOrderAddress();
        if (! $shippingOrderAddress instanceof OrderAddressEntity) {
            throw new \Exception('Order does not have a shipping address');
        }
        $shippingAddress = Address::fromAddress($customer, $shippingOrderAddress);

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
        $payment->setBillingAddress($billingAddress);
        $payment->setShippingAddress($shippingAddress);
        $payment->setLines($lineItemCollection);
        $payment->setLocale(Locale::fromLanguage($language));
        $payment->setWebhookUrl($webhookUrl);
        $payment->setShopwareOrderNumber($orderNumber);

        return $payment;
    }
}
