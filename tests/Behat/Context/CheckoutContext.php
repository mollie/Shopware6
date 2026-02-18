<?php

declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Mollie\Shopware\Behat\Storage;
use Mollie\Shopware\Component\Mollie\Gateway\CachedMollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Mollie\Shopware\Integration\Data\CheckoutTestBehaviour;
use Mollie\Shopware\Integration\Data\PaymentMethodTestBehaviour;
use Mollie\Shopware\Integration\MolliePage\MolliePage;
use PHPUnit\Framework\Assert;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Api\OrderActionController;
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

    /**
     * @BeforeScenario
     */
    public function setUp(): void
    {
    }

    #[Given('product :arg1 with quantity :arg2 is in cart')]
    public function productWithQuantityIsInCart(string $productNumber, int $quantity): void
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $this->addItemToCart($productNumber, $salesChannelContext, $quantity);
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
        $order = $this->getOrderById($orderId, $this->getCurrentSalesChannelContext());
        /** @var OrderTransactionEntity $oderTransaction */
        $oderTransaction = $order->getTransactions()->first();
        $actualOrderState = $oderTransaction->getStateMachineState()->getTechnicalName();

        Assert::assertSame($expectedPaymentStatus, $actualOrderState);
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
}
