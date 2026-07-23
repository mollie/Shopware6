<?php

declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Mollie\Shopware\Behat\Storage;
use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\Gateway\CachedMollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\ShippingItemCollection;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Mollie\Shopware\Component\Shipment\Route\CancelItemRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipmentApiRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Mollie\Shopware\Integration\Data\CheckoutTestBehaviour;
use Mollie\Shopware\Integration\Data\PaymentMethodTestBehaviour;
use Mollie\Shopware\Integration\MolliePage\MolliePage;
use Mollie\Shopware\Mollie;
use PHPUnit\Framework\Assert;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Api\OrderActionController;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class CheckoutContext extends ShopwareContext
{
    use CheckoutTestBehaviour;
    use PaymentMethodTestBehaviour;
    public const STORAGE_MOLLIE_URL = 'mollieUrl';
    public const STORAGE_ORDER_ID = 'orderId';
    public const STORAGE_RETURN_URL = 'shopwareReturnUrl';
    public const STORAGE_REMEMBERED_PAYMENT_ID = 'rememberedMolliePaymentId';

    #[BeforeScenario]
    public function setUp(): void
    {
    }

    #[Given('product :arg1 with quantity :arg2 is in cart')]
    public function productWithQuantityIsInCart(string $productNumber, int $quantity): void
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $this->addItemToCart($productNumber, $salesChannelContext, $quantity);
    }

    #[Given('product :arg1 with quantity :arg2 is in cart as subscription')]
    public function productWithQuantityIsInCartAsSubscription(string $productNumber, int $quantity): void
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $this->addSubscriptionItemToCart($productNumber, $salesChannelContext, $quantity);
    }

    #[Given('i apply promotion code :arg1')]
    public function iApplyPromotionCode(string $code): void
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $this->addPromotionToCart($code, $salesChannelContext);
    }

    #[When('i start checkout with payment method :arg1')]
    public function iStartCheckoutWithPaymentMethod(string $paymentMethodTechnicalName): void
    {
        $paymentMethod = $this->getPaymentMethodByTechnicalName($paymentMethodTechnicalName, $this->getCurrentSalesChannelContext()->getContext());

        $this->setOptions(SalesChannelContextService::PAYMENT_METHOD_ID, $paymentMethod->getId());

        /** @var RedirectResponse $response */
        $response = $this->startCheckout($this->getCurrentSalesChannelContext());

        $mollieSandboxPage = $response->getTargetUrl();

        Storage::set(self::STORAGE_MOLLIE_URL, $mollieSandboxPage);
        Assert::assertStringContainsString('mollie.com', $mollieSandboxPage);
    }

    #[When('select payment status :arg1')]
    public function selectPaymentStatus(string $selectedStatus): void
    {
        $mollieUrl = Storage::get(self::STORAGE_MOLLIE_URL);
        $molliePage = new MolliePage($mollieUrl);
        $response = $molliePage->selectPaymentStatus($selectedStatus);
        Assert::assertSame($response->getStatusCode(), 302);
        $redirect = $response->getHeaderLine('location');

        if (str_contains($redirect, 'mollie.com')) {
            Storage::set(self::STORAGE_MOLLIE_URL, $redirect);

            return;
        }
        Storage::set(self::STORAGE_RETURN_URL, $redirect);
    }

    #[When('i select issuer :arg1')]
    public function iSelectIssuer(string $issuer): void
    {
        $mollieUrl = Storage::get(self::STORAGE_MOLLIE_URL);
        $molliePage = new MolliePage($mollieUrl);
        $response = $molliePage->selectIssuer($issuer);

        Assert::assertSame($response->getStatusCode(), 302);
        $mollieUrl = $response->getHeaderLine('location');
        Storage::set(self::STORAGE_MOLLIE_URL, $mollieUrl);
        Assert::assertStringContainsString('mollie.com', $mollieUrl);
    }

    #[Given('i select :art1 as currency')]
    public function iSelectAsCurrency(string $currency): void
    {
        $currency = $this->findCurrencyByIso($currency, $this->getCurrentSalesChannelContext());
        $this->setOptions(SalesChannelContextService::CURRENCY_ID, $currency->getId());
    }

    #[Then('i see success page')]
    public function iSeeSuccessPage(): void
    {
        $returnPage = Storage::get(self::STORAGE_RETURN_URL, '');
        if (strlen($returnPage) === 0) {
            $mollieUrl = Storage::get(self::STORAGE_MOLLIE_URL);
            $molliePage = new MolliePage($mollieUrl);
            $returnPage = $molliePage->getShopwareReturnPage();
            Storage::set(self::STORAGE_RETURN_URL, $returnPage);
        }
        Assert::assertStringContainsString('mollie/', $returnPage);
        /** @var RedirectResponse $response */
        $response = $this->finishCheckout($returnPage, $this->getCurrentSalesChannelContext());
        $shopwareOderId = str_replace('/checkout/finish?orderId=', '', $response->getTargetUrl());

        Assert::assertSame($response->getStatusCode(), 302);
        Assert::assertNotEmpty($shopwareOderId);
        Storage::set(self::STORAGE_ORDER_ID,$shopwareOderId);
    }

    #[When('select mollie payment method :arg1')]
    public function selectMolliePaymentMethod(string $molliePaymentMethod): void
    {
        $mollieUrl = Storage::get(self::STORAGE_MOLLIE_URL);
        $molliePage = new MolliePage($mollieUrl);
        $response = $molliePage->selectPaymentMethod($molliePaymentMethod);

        Assert::assertSame($response->getStatusCode(), 302);
        $mollieUrl = $response->getHeaderLine('location');
        Assert::assertStringContainsString('mollie.com', $mollieUrl);
        Storage::set(self::STORAGE_MOLLIE_URL, $mollieUrl);
    }

    #[Then('order payment status is :arg1')]
    public function orderPaymentStatusIs(string $expectedPaymentStatus): void
    {
        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $salesChannelContext = $this->getCurrentSalesChannelContext();

        $order = $this->getOrderById($orderId, $salesChannelContext);
        /** @var OrderTransactionEntity $oderTransaction */
        $oderTransaction = $order->getTransactions()->first();
        $actualOrderState = $oderTransaction->getStateMachineState()->getTechnicalName();

        // Mollie can take a few seconds to move an authorized payment to "paid" after the shipment
        // capture, and the shop only re-syncs once (DevWebHookSubscriber). When the status has not
        // caught up yet, re-fire the webhook sync (as Mollie would retry the webhook) and re-read.
        // This is test-only polling; the plugin behaviour is not changed.
        /** @var WebhookRoute $webhookRoute */
        $webhookRoute = $this->getContainer()->get(WebhookRoute::class);
        /** @var CachedMollieGateway $mollieGateway */
        $mollieGateway = $this->getContainer()->get(MollieGateway::class);

        $attempt = 0;
        while ($actualOrderState !== $expectedPaymentStatus && $attempt < 5) {
            ++$attempt;
            sleep(2);
            $mollieGateway->clearCache();
            $webhookRoute->notify($oderTransaction->getId(), $salesChannelContext->getContext());

            $order = $this->getOrderById($orderId, $salesChannelContext);
            /** @var OrderTransactionEntity $oderTransaction */
            $oderTransaction = $order->getTransactions()->first();
            $actualOrderState = $oderTransaction->getStateMachineState()->getTechnicalName();
        }

        Assert::assertSame($expectedPaymentStatus, $actualOrderState);
    }

    #[Then('order total is :arg1')]
    public function orderTotalIs(string $expectedTotal): void
    {
        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $order = $this->getOrderById($orderId, $this->getCurrentSalesChannelContext());

        Assert::assertSame((float) $expectedTotal, $order->getAmountTotal(), sprintf('Order %s total mismatch', $orderId));
    }

    #[Then('the mollie captured amount equals the order total')]
    public function theMollieCapturedAmountEqualsTheOrderTotal(): void
    {
        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $order = $this->getOrderById($orderId, $salesChannelContext);

        /** @var OrderTransactionEntity $transaction */
        $transaction = $order->getTransactions()->first();
        Assert::assertNotNull($transaction, sprintf('No transaction found for order %s', $orderId));

        /** @var CachedMollieGateway $mollieGateway */
        $mollieGateway = $this->getContainer()->get(MollieGateway::class);

        $expectedTotal = $order->getAmountTotal();
        $attempt = 0;
        $capturedValue = null;
        while ($attempt < 5) {
            $mollieGateway->clearCache();
            $payment = $mollieGateway->getPaymentByTransactionId($transaction->getId(), $salesChannelContext->getContext());
            $capturedAmount = $payment->getCapturedAmount();
            if ($capturedAmount !== null && abs($capturedAmount->getValue() - $expectedTotal) <= 0.01) {
                return;
            }
            $capturedValue = $capturedAmount?->getValue();
            ++$attempt;
            sleep(2);
        }

        Assert::fail(sprintf(
            'Mollie captured amount %s does not match the gross order total %.2f for order %s',
            $capturedValue === null ? 'null' : sprintf('%.2f', $capturedValue),
            $expectedTotal,
            $orderId
        ));
    }

    #[Then('the mollie captured amount matches the shipped gross amount')]
    public function theMollieCapturedAmountMatchesTheShippedGrossAmount(): void
    {
        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $order = $this->getOrderById($orderId, $salesChannelContext);

        $taxStatus = (string) $order->getTaxStatus();
        $expectedGross = 0.0;

        foreach ($order->getLineItems() ?? new OrderLineItemCollection() as $lineItem) {
            $shippedQty = (int) (($lineItem->getCustomFields()[Mollie::EXTENSION] ?? [])['quantity'] ?? 0);
            $price = $lineItem->getPrice();
            if ($shippedQty <= 0 || ! $price instanceof CalculatedPrice) {
                continue;
            }
            $expectedGross += $this->grossPortion($price, $lineItem->getQuantity(), $shippedQty, $taxStatus);
        }

        foreach ($order->getDeliveries() ?? new OrderDeliveryCollection() as $delivery) {
            $shippedQty = (int) (($delivery->getCustomFields()[Mollie::EXTENSION] ?? [])['quantity'] ?? 0);
            if ($shippedQty <= 0) {
                continue;
            }
            $shippingCosts = $delivery->getShippingCosts();
            $expectedGross += $this->grossPortion($shippingCosts, $shippingCosts->getQuantity(), $shippedQty, $taxStatus);
        }

        /** @var OrderTransactionEntity $transaction */
        $transaction = $order->getTransactions()->first();
        Assert::assertNotNull($transaction, sprintf('No transaction found for order %s', $orderId));

        /** @var CachedMollieGateway $mollieGateway */
        $mollieGateway = $this->getContainer()->get(MollieGateway::class);

        // A partial capture (the rest of the authorization is released asynchronously) may take a
        // moment to be reflected in the payment's amountCaptured, so poll a few times.
        $attempt = 0;
        $capturedValue = null;
        while ($attempt < 5) {
            $mollieGateway->clearCache();
            $payment = $mollieGateway->getPaymentByTransactionId($transaction->getId(), $salesChannelContext->getContext());
            $capturedAmount = $payment->getCapturedAmount();
            if ($capturedAmount !== null && abs($capturedAmount->getValue() - $expectedGross) <= 0.01) {
                return;
            }
            $capturedValue = $capturedAmount?->getValue();
            ++$attempt;
            sleep(2);
        }

        Assert::fail(sprintf(
            'Mollie captured amount %s does not match the shipped gross amount %.2f for order %s',
            $capturedValue === null ? 'null' : sprintf('%.2f', $capturedValue),
            $expectedGross,
            $orderId
        ));
    }

    #[Then('the order shipping country is :arg1')]
    public function theOrderShippingCountryIs(string $expectedIsoCode): void
    {
        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $order = $this->getOrderById($orderId, $this->getCurrentSalesChannelContext());

        /** @var ?OrderDeliveryEntity $delivery */
        $delivery = $order->getDeliveries()?->first();
        Assert::assertNotNull($delivery, sprintf('Order %s has no delivery', $orderId));

        $shippingAddress = $delivery->getShippingOrderAddress();
        Assert::assertNotNull($shippingAddress, sprintf('Order %s delivery has no shipping address', $orderId));

        $country = $shippingAddress->getCountry();
        Assert::assertNotNull($country, sprintf('Order %s shipping address has no country', $orderId));

        Assert::assertSame($expectedIsoCode, $country->getIso(), sprintf('Order %s shipping country mismatch', $orderId));
    }

    #[Then('i remember the mollie payment id')]
    public function iRememberTheMolliePaymentId(): void
    {
        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $order = $this->getOrderById($orderId, $this->getCurrentSalesChannelContext());

        /** @var ?OrderTransactionEntity $transaction */
        $transaction = $order->getTransactions()->first();
        Assert::assertNotNull($transaction, sprintf('No transaction found for order %s', $orderId));

        $customFields = $transaction->getCustomFields() ?? [];
        $mollieData = $customFields[Mollie::EXTENSION] ?? [];
        $paymentId = $mollieData['id'] ?? null;

        Assert::assertNotEmpty($paymentId, sprintf('No Mollie payment id on transaction of order %s', $orderId));

        Storage::set(self::STORAGE_REMEMBERED_PAYMENT_ID, $paymentId);
    }

    #[When('i select delivery status action :arg1')]
    public function iSelectDeliveryStatusAction(string $targetStatus): void
    {
        /** @var OrderActionController $orderActionController */
        $orderActionController = $this->getContainer()->get(OrderActionController::class);
        /** @var CachedMollieGateway $mollieGateway */
        $mollieGateway = $this->getContainer()->get(MollieGateway::class);
        $mollieGateway->clearCache();
        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $order = $this->getOrderById($orderId, $this->getCurrentSalesChannelContext());
        $firstDelivery = $order->getDeliveries()->first();
        $orderDeliveryId = $firstDelivery->getId();
        $request = new Request();
        $request->request->set('sendMail', false);

        $response = $orderActionController->orderDeliveryStateTransition($orderDeliveryId, $targetStatus, $request, $this->getCurrentSalesChannelContext()->getContext());
    }

    #[When('i cancel line item :arg1 with quantity :arg2')]
    public function iCancelLineItemWithQuantity(string $productNumber, int $quantity): void
    {
        /** @var CancelItemRoute $cancelItemRoute */
        $cancelItemRoute = $this->getContainer()->get(CancelItemRoute::class);
        /** @var CachedMollieGateway $mollieGateway */
        $mollieGateway = $this->getContainer()->get(MollieGateway::class);
        $mollieGateway->clearCache();

        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $order = $this->getOrderById($orderId, $salesChannelContext);

        $lineItem = ($order->getLineItems() ?? new OrderLineItemCollection())->firstWhere(
            function (OrderLineItemEntity $item) use ($productNumber) {
                return $item->getProduct()?->getProductNumber() === $productNumber;
            }
        );
        Assert::assertNotNull($lineItem, sprintf('Line item for product %s not found on order %s', $productNumber, $orderId));

        $request = new Request();
        $request->request->set('shopwareLineId', $lineItem->getId());
        $request->request->set('quantity', $quantity);

        $cancelItemRoute->cancel($request, $salesChannelContext->getContext());
    }

    #[When('i ship line item :arg1 with quantity :arg2')]
    public function iShipLineItemWithQuantity(string $productNumber, int $quantity): void
    {
        /** @var ShipOrderRoute $shipOrderRoute */
        $shipOrderRoute = $this->getContainer()->get(ShipOrderRoute::class);
        /** @var CachedMollieGateway $mollieGateway */
        $mollieGateway = $this->getContainer()->get(MollieGateway::class);
        $mollieGateway->clearCache();

        $items = [
            [
                'id' => $productNumber,
                'quantity' => $quantity,
            ],
        ];
        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $request = new Request();
        $request->request->set('orderId', $orderId);
        $request->request->set('items', $items);

        $shipOrderRoute->ship($request, $this->getCurrentSalesChannelContext()->getContext());
    }

    /**
     * Reproduces a legacy, buggy order: only the net amount was captured at Mollie (the taxes are
     * still authorized) and all line items/deliveries are marked as shipped in Shopware.
     */
    #[When('the order is captured with the net amount only and marked as shipped')]
    public function theOrderIsCapturedWithTheNetAmountOnlyAndMarkedAsShipped(): void
    {
        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $context = $salesChannelContext->getContext();
        $order = $this->getOrderById($orderId, $salesChannelContext);

        $currency = $order->getCurrency();
        Assert::assertNotNull($currency, sprintf('Order %s has no currency', $orderId));

        $netAmount = 0.0;
        $lineUpserts = [];
        foreach ($order->getLineItems() ?? new OrderLineItemCollection() as $lineItem) {
            $price = $lineItem->getPrice();
            if (! $price instanceof CalculatedPrice) {
                continue;
            }
            $netAmount += $price->getTotalPrice();
            $extension = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
            $lineUpserts[] = [
                'id' => $lineItem->getId(),
                'customFields' => [
                    Mollie::EXTENSION => array_merge($extension, ['quantity' => $lineItem->getQuantity()]),
                ],
            ];
        }

        $deliveryUpserts = [];
        foreach ($order->getDeliveries() ?? new OrderDeliveryCollection() as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            $netAmount += $shippingCosts->getTotalPrice();
            $extension = $delivery->getCustomFields()[Mollie::EXTENSION] ?? [];
            $deliveryUpserts[] = [
                'id' => $delivery->getId(),
                'customFields' => [
                    Mollie::EXTENSION => array_merge($extension, ['quantity' => $shippingCosts->getQuantity()]),
                ],
            ];
        }

        /** @var OrderTransactionEntity $transaction */
        $transaction = $order->getTransactions()->first();
        Assert::assertNotNull($transaction, sprintf('No transaction found for order %s', $orderId));

        /** @var CachedMollieGateway $mollieGateway */
        $mollieGateway = $this->getContainer()->get(MollieGateway::class);
        $mollieGateway->clearCache();
        $payment = $mollieGateway->getPaymentByTransactionId($transaction->getId(), $context);

        $emptyItems = new ShippingItemCollection();
        $legacyCapture = new CreateCapture($emptyItems, $currency->getIsoCode());
        $legacyAmount = new Money($netAmount, $currency->getIsoCode());
        $legacyCapture->setAmount($legacyAmount);
        $legacyCapture->setDescription('legacy net capture');
        $mollieGateway->createCapture($legacyCapture, $payment->getId(), (string) $order->getOrderNumber(), $order->getSalesChannelId());

        /** @var EntityRepository $lineRepository */
        $lineRepository = $this->getContainer()->get('order_line_item.repository');
        $lineRepository->upsert($lineUpserts, $context);

        /** @var EntityRepository $deliveryRepository */
        $deliveryRepository = $this->getContainer()->get('order_delivery.repository');
        $deliveryRepository->upsert($deliveryUpserts, $context);
    }

    #[When('i ship the order via the operational api')]
    public function iShipTheOrderViaTheOperationalApi(): void
    {
        /** @var ShipmentApiRoute $shipmentApiRoute */
        $shipmentApiRoute = $this->getContainer()->get(ShipmentApiRoute::class);
        /** @var CachedMollieGateway $mollieGateway */
        $mollieGateway = $this->getContainer()->get(MollieGateway::class);
        $mollieGateway->clearCache();

        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $order = $this->getOrderById($orderId, $salesChannelContext);
        $orderNumber = (string) $order->getOrderNumber();

        $content = (string) json_encode(['orderNumber' => $orderNumber]);
        $request = new Request([], [], [], [], [], [], $content);

        $shipmentApiRoute->shipOrder($request, $salesChannelContext->getContext());
    }

    #[Then('delivery status is :arg1')]
    public function deliveryStatusIs(string $expectedDeliveryStatus): void
    {
        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $order = $this->getOrderById($orderId, $this->getCurrentSalesChannelContext());
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $order->getDeliveries()->first();
        $actualDeliveryStatus = $orderDelivery->getStateMachineState()->getTechnicalName();

        Assert::assertSame($expectedDeliveryStatus, $actualDeliveryStatus);
    }

    /**
     * Gross value of the shipped portion, derived from Shopware's own calculated taxes so it stays
     * independent of the plugin's capture logic. For net-tax orders the tax from getCalculatedTaxes()
     * is added on top of the (net) price.
     */
    private function grossPortion(CalculatedPrice $price, int $totalQty, int $shippedQty, string $taxStatus): float
    {
        $gross = $price->getTotalPrice();
        if ($taxStatus === CartPrice::TAX_STATE_NET) {
            $gross += $price->getCalculatedTaxes()->getAmount();
        }

        if ($totalQty <= 0) {
            return $gross;
        }

        return $gross / $totalQty * $shippedQty;
    }
}
