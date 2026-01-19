<?php

declare(strict_types=1);

namespace Mollie\Shopware\Behat;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Mollie\Shopware\Component\Mollie\Gateway\CachedMollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Settings\SettingsService;
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

    private string $mollieSandboxPage = '';
    private string $shopwareReturnPage = '';
    private string $shopwareOderId = '';

    /**
     * @BeforeScenario
     */
    public function setUp(): void
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->getContainer()->get(SettingsService::class);
        $settingsService->clearCache();
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

        $this->mollieSandboxPage = $response->getTargetUrl();
        Assert::assertStringContainsString('mollie.com', $this->mollieSandboxPage);
    }

    #[When('select payment status :arg1')]
    public function selectPaymentStatus(string $selectedStatus): void
    {
        $molliePage = new MolliePage($this->mollieSandboxPage);
        $response = $molliePage->selectPaymentStatus($selectedStatus);
        Assert::assertSame($response->getStatusCode(), 302);
        $redirect = $response->getHeaderLine('location');

        if (str_contains($redirect, 'mollie.com')) {
            $this->mollieSandboxPage = $redirect;

            return;
        }

        $this->shopwareReturnPage = $redirect;
    }

    #[When('i select issuer :arg1')]
    public function iSelectIssuer(string $issuer): void
    {
        $molliePage = new MolliePage($this->mollieSandboxPage);
        $response = $molliePage->selectIssuer($issuer);

        Assert::assertSame($response->getStatusCode(), 302);
        $this->mollieSandboxPage = $response->getHeaderLine('location');
        Assert::assertStringContainsString('mollie.com', $this->mollieSandboxPage);
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
        $this->shopwareOderId = '';
        $returnPage = $this->shopwareReturnPage;
        if (strlen($returnPage) === 0) {
            $molliePage = new MolliePage($this->mollieSandboxPage);
            $returnPage = $molliePage->getShopwareReturnPage();
            $this->shopwareReturnPage = $returnPage;
        }
        Assert::assertStringContainsString('mollie/', $returnPage);
        /** @var RedirectResponse $response */
        $response = $this->finishCheckout($returnPage, $this->getCurrentSalesChannelContext());
        $this->shopwareOderId = str_replace('/checkout/finish?orderId=', '', $response->getTargetUrl());

        Assert::assertSame($response->getStatusCode(), 302);
        Assert::assertNotEmpty($this->shopwareOderId);
    }

    #[When('select mollie payment method :arg1')]
    public function selectMolliePaymentMethod(string $molliePaymentMethod): void
    {
        $molliePage = new MolliePage($this->mollieSandboxPage);
        $response = $molliePage->selectPaymentMethod($molliePaymentMethod);

        Assert::assertSame($response->getStatusCode(), 302);
        $this->mollieSandboxPage = $response->getHeaderLine('location');

        Assert::assertStringContainsString('mollie.com', $this->mollieSandboxPage);
    }

    #[Then('order payment status is :arg1')]
    public function orderPaymentStatusIs(string $expectedPaymentStatus): void
    {
        $order = $this->getOrderById($this->shopwareOderId, $this->getCurrentSalesChannelContext());
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
        $order = $this->getOrderById($this->shopwareOderId, $this->getCurrentSalesChannelContext());
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

        $request = new Request();
        $request->request->set('orderId', $this->shopwareOderId);
        $request->request->set('items', $items);

        $shipOrderRoute->ship($request, $this->getCurrentSalesChannelContext()->getContext());
    }

    #[Then('delivery status is :arg1')]
    public function deliveryStatusIs(string $expectedDeliveryStatus): void
    {
        $order = $this->getOrderById($this->shopwareOderId, $this->getCurrentSalesChannelContext());
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $order->getDeliveries()->first();
        $actualDeliveryStatus = $orderDelivery->getStateMachineState()->getTechnicalName();
        // dump($actualDeliveryStatus);

        Assert::assertSame($expectedDeliveryStatus, $actualDeliveryStatus);
    }
}
