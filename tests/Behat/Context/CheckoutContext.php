<?php

declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Mollie\Shopware\Behat\Storage;
use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\Gateway\CachedMollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\ShippingItemCollection;
use Mollie\Shopware\Component\Payment\Mandate\Route\ListMandatesRoute;
use Mollie\Shopware\Component\Payment\Method\CardPayment;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Shipment\Route\CancelItemRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipmentApiRoute;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Mollie\Shopware\Integration\Data\CheckoutTestBehaviour;
use Mollie\Shopware\Integration\Data\PaymentMethodTestBehaviour;
use Mollie\Shopware\Integration\Mollie\CardTokenizer;
use Mollie\Shopware\Integration\MolliePage\MolliePage;
use Mollie\Shopware\Mollie;
use PHPUnit\Framework\Assert;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Api\OrderActionController;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
    public const STORAGE_CARD_TOKEN = 'creditCardToken';
    public const STORAGE_SAVE_PAYMENT_DETAILS = 'savePaymentDetails';
    public const STORAGE_MANDATE_ID = 'mandateId';
    public const STORAGE_MANDATE_IDS = 'mandateIds';
    public const STORAGE_CREATED_MANDATE_ID = 'createdMandateId';
    public const STORAGE_MOLLIE_CUSTOMER_ID = 'mollieCustomerId';

    /**
     * Custom field and Mollie prefix per id type, as an accounting export reads them from the order.
     */
    private const EXPORT_IDS = [
        'refund' => ['customField' => 'refundIds', 'prefix' => 're-'],
        'capture' => ['customField' => 'captureIds', 'prefix' => 'cpt-'],
        'shipment' => ['customField' => 'shipmentIds', 'prefix' => 'shp-'],
    ];

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

    /**
     * In the storefront mollie.js exchanges the card data for this token inside an iframe, so the
     * shop never holds a card number - it is not allowed to. Behat has no browser to run mollie.js
     * in, so CardTokenizer does that one step for a published test card. See its warning.
     */
    #[Given('i use the test credit card :arg1')]
    public function iUseTheTestCreditCard(string $cardBrand): void
    {
        $salesChannelId = $this->getCurrentSalesChannelContext()->getSalesChannelId();

        /** @var SettingsService $settingsService */
        $settingsService = $this->getContainer()->get(SettingsService::class);
        $apiSettings = $settingsService->getApiSettings($salesChannelId);

        $profileId = $this->resolveProfileId();

        $cardTokenizer = new CardTokenizer();
        $cardToken = $cardTokenizer->createCardToken($cardBrand, $profileId, $apiSettings->isTestMode(), $_ENV['APP_URL']);

        Storage::set(self::STORAGE_CARD_TOKEN, $cardToken);
    }

    /**
     * The checkbox the card template renders next to the card fields.
     */
    #[Given('i want to save the credit card')]
    public function iWantToSaveTheCreditCard(): void
    {
        Storage::set(self::STORAGE_SAVE_PAYMENT_DETAILS, true);
    }

    #[When('i start checkout with payment method :arg1')]
    public function iStartCheckoutWithPaymentMethod(string $paymentMethodTechnicalName): void
    {
        $paymentMethod = $this->getPaymentMethodByTechnicalName($paymentMethodTechnicalName, $this->getCurrentSalesChannelContext()->getContext());

        $this->setOptions(SalesChannelContextService::PAYMENT_METHOD_ID, $paymentMethod->getId());

        /** @var RedirectResponse $response */
        $response = $this->startCheckout($this->getCurrentSalesChannelContext(), $this->buildPaymentData());

        $mollieSandboxPage = $response->getTargetUrl();

        Storage::set(self::STORAGE_MOLLIE_URL, $mollieSandboxPage);
        Assert::assertStringContainsString('mollie.com', $mollieSandboxPage);
    }

    /**
     * Paying a mandate still goes through Mollie, so the status is picked on the sandbox page as in
     * any other checkout.
     */
    #[When('i pay with the stored mandate for payment method :arg1')]
    public function iPayWithTheStoredMandateForPaymentMethod(string $paymentMethodTechnicalName): void
    {
        $mandateId = Storage::get(self::STORAGE_MANDATE_ID, '');
        Assert::assertNotSame('', $mandateId, 'No mandate was remembered to pay with');

        // A stored card is paid without entering card data again, which is the whole point of the
        // mandate - so the token of the previous order must not be sent along.
        Storage::set(self::STORAGE_CARD_TOKEN, '');
        Storage::set(self::STORAGE_SAVE_PAYMENT_DETAILS, false);

        $this->iStartCheckoutWithPaymentMethod($paymentMethodTechnicalName);
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

    /**
     * What the setting actually promises. Mollie creates a customer-present mandate for any card
     * payment that carries a customer id, so the mandates at Mollie are not ours to assert - but
     * whether the shop lets the customer pay with one is.
     */
    #[Then('the shop offers no stored cards for payment method :arg1')]
    public function theShopOffersNoStoredCardsForPaymentMethod(string $paymentMethodTechnicalName): void
    {
        /** @var ListMandatesRoute $listMandatesRoute */
        $listMandatesRoute = $this->getContainer()->get(ListMandatesRoute::class);

        $mandates = $listMandatesRoute->list('', $this->getCurrentSalesChannelContext())
            ->getMandates()
            ->filterByPaymentMethod(PaymentMethod::from($paymentMethodTechnicalName))
        ;

        Assert::assertCount(0, $mandates, sprintf('The shop offers %d stored cards', $mandates->count()));
    }

    #[Then('the mollie page offers no payment status selection')]
    public function theMolliePageOffersNoPaymentStatusSelection(): void
    {
        $molliePage = new MolliePage(Storage::get(self::STORAGE_MOLLIE_URL));

        Assert::assertFalse(
            $molliePage->hasPaymentStatusSelection(),
            'Mollie offered a payment status selection, so it got a card token instead of collecting the card itself'
        );
    }

    /**
     * The mandates of the fixture customer survive a test run, so only the change a scenario causes
     * can be asserted, never an absolute count. The ids are kept as well, because Mollie does not
     * promise an order for the list and the new mandate is the one that was not there before.
     */
    #[Given('i remember the number of mandates for payment method :arg1')]
    public function iRememberTheNumberOfMandatesForPaymentMethod(string $paymentMethodTechnicalName): void
    {
        Storage::set(self::STORAGE_MANDATE_IDS, $this->findMandates($paymentMethodTechnicalName)->getKeys());
    }

    #[Then('the number of mandates for payment method :arg1 increased by :arg2')]
    public function theNumberOfMandatesForPaymentMethodIncreasedBy(string $paymentMethodTechnicalName, int $expectedIncrease): void
    {
        $idsBefore = Storage::get(self::STORAGE_MANDATE_IDS);
        Assert::assertIsArray($idsBefore, 'The mandates were not remembered before the payment');

        // Mollie creates the mandate while it finishes the payment, so it can show up a moment late.
        // "No new mandate" therefore has to hold for a while, not just at the first look.
        $newIds = [];
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $newIds = array_values(array_diff($this->findMandates($paymentMethodTechnicalName)->getKeys(), $idsBefore));
            if (count($newIds) === $expectedIncrease && $expectedIncrease > 0) {
                break;
            }
            sleep(2);
        }

        Assert::assertCount(
            $expectedIncrease,
            $newIds,
            sprintf('Expected %d new mandates, got "%s"', $expectedIncrease, implode(',', $newIds))
        );

        if (count($newIds) === 1) {
            Storage::set(self::STORAGE_MANDATE_ID, $newIds[0]);
            Storage::set(self::STORAGE_CREATED_MANDATE_ID, $newIds[0]);
        }
    }

    /**
     * Every run would otherwise leave the fixture customer one mandate richer, until the list Mollie
     * returns is paginated and the comparison above stops seeing the mandates it counted.
     */
    #[AfterScenario]
    public function revokeTheCreatedMandate(): void
    {
        $mandateId = Storage::get(self::STORAGE_CREATED_MANDATE_ID, '');
        $mollieCustomerId = Storage::get(self::STORAGE_MOLLIE_CUSTOMER_ID, '');
        if ($mandateId === '' || $mollieCustomerId === '') {
            return;
        }

        /** @var CachedMollieGateway $mollieGateway */
        $mollieGateway = $this->getContainer()->get(MollieGateway::class);
        $mollieGateway->revokeMandate($mollieCustomerId, $mandateId, $this->getCurrentSalesChannelContext()->getSalesChannelId());
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
        $legacyCapture = new CreateCapture($emptyItems, $currency->getIsoCode(), 'legacy net capture');
        $legacyAmount = new Money($netAmount, $currency->getIsoCode());
        $legacyCapture->setAmount($legacyAmount);
        $mollieGateway->createCapture($legacyCapture, $payment->getId(), (string) $order->getOrderNumber(), $order->getSalesChannelId());

        // Mollie processes captures asynchronously; wait until the net capture is reflected on the
        // payment before the order is shipped, so the reconciliation reads a stable state (captured =
        // net, remaining = tax) instead of racing the API and reporting "nothing to reconcile".
        $attempt = 0;
        while ($attempt < 10) {
            $mollieGateway->clearCache();
            $freshPayment = $mollieGateway->getPayment($payment->getId(), (string) $order->getOrderNumber(), $order->getSalesChannelId());
            $capturedAmount = $freshPayment->getCapturedAmount()?->getValue() ?? 0.0;
            if ($capturedAmount >= $netAmount - 0.005) {
                break;
            }
            ++$attempt;
            sleep(1);
        }

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

    #[Then('the order has :count :idType ids')]
    public function theOrderHasIds(int $count, string $idType): void
    {
        $customField = self::EXPORT_IDS[$idType]['customField'];
        $prefix = self::EXPORT_IDS[$idType]['prefix'];

        $orderId = Storage::get(self::STORAGE_ORDER_ID);
        $order = $this->getOrderById($orderId, $this->getCurrentSalesChannelContext());

        $mollieCustomFields = ($order->getCustomFields() ?? [])[Mollie::EXTENSION] ?? [];
        $storedIds = (string) ($mollieCustomFields[$customField] ?? '');

        $ids = array_values(array_filter(array_map('trim', explode(',', $storedIds)), function (string $id): bool {
            return strlen($id) > 0;
        }));

        Assert::assertCount($count, $ids, sprintf('Order %s has the %s ids "%s"', $orderId, $idType, $storedIds));

        foreach ($ids as $id) {
            // The accounting export needs the Mollie underscore replaced by a hyphen.
            Assert::assertStringStartsWith($prefix, $id, sprintf('Id "%s" is not a %s id in export format', $id, $idType));
            Assert::assertStringNotContainsString('_', $id, sprintf('Id "%s" still carries the Mollie underscore', $id));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPaymentData(): array
    {
        $paymentData = [];

        $cardToken = Storage::get(self::STORAGE_CARD_TOKEN, '');
        if ($cardToken !== '') {
            $paymentData[CardPayment::FIELD_CREDIT_CARD_TOKEN] = $cardToken;
        }

        if (Storage::get(self::STORAGE_SAVE_PAYMENT_DETAILS, false)) {
            $paymentData[CardPayment::FIELD_SAVE_PAYMENT_DETAILS] = true;
        }

        $mandateId = Storage::get(self::STORAGE_MANDATE_ID, '');
        if ($mandateId !== '') {
            $paymentData[CardPayment::FIELD_MANDATE_ID] = $mandateId;
        }

        return $paymentData;
    }

    /**
     * The profile the payment is created on. A card token belongs to one profile and Mollie rejects
     * it on another, and the Mollie customer id is stored per profile as well.
     */
    private function resolveProfileId(): string
    {
        $salesChannelId = $this->getCurrentSalesChannelContext()->getSalesChannelId();

        /** @var SettingsService $settingsService */
        $settingsService = $this->getContainer()->get(SettingsService::class);
        $profileId = $settingsService->getApiSettings($salesChannelId)->getProfileId();
        if (mb_strlen($profileId) > 0) {
            return $profileId;
        }

        /** @var CachedMollieGateway $mollieGateway */
        $mollieGateway = $this->getContainer()->get(MollieGateway::class);

        return $mollieGateway->getCurrentProfile($salesChannelId)->getId();
    }

    private function findMandates(string $paymentMethodTechnicalName): MandateCollection
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        /** @var SettingsService $settingsService */
        $settingsService = $this->getContainer()->get(SettingsService::class);
        $mode = $settingsService->getApiSettings($salesChannelId)->getMode();

        // Read fresh: the sales channel context is built once per scenario, so its customer does not
        // carry the Mollie customer id the payment just wrote.
        /** @var EntityRepository $customerRepository */
        $customerRepository = $this->getContainer()->get('customer.repository');
        /** @var ?CustomerEntity $customer */
        $customer = $customerRepository->search(new Criteria([(string) $salesChannelContext->getCustomer()?->getId()]), $salesChannelContext->getContext())->first();
        Assert::assertNotNull($customer, 'The logged in customer was not found');

        $customerIds = ($customer->getCustomFields() ?? [])[Mollie::EXTENSION]['customer_ids'] ?? [];
        $mollieCustomerId = $customerIds[$this->resolveProfileId()][$mode->value] ?? null;
        if ($mollieCustomerId === null) {
            return new MandateCollection();
        }
        Storage::set(self::STORAGE_MOLLIE_CUSTOMER_ID, $mollieCustomerId);

        /** @var CachedMollieGateway $mollieGateway */
        $mollieGateway = $this->getContainer()->get(MollieGateway::class);
        $mollieGateway->clearCache();

        return $mollieGateway->listMandates($mollieCustomerId, $salesChannelId)
            ->filterByPaymentMethod(PaymentMethod::from($paymentMethodTechnicalName))
        ;
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
