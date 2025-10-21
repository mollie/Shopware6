<?php

namespace Mollie\Behat;



use Mollie\Integration\Data\CheckoutTestBehaviour;
use Mollie\Integration\Data\MolliePageTestBehaviour;
use Mollie\Integration\Data\PaymentMethodTestBehaviour;
use PHPUnit\Framework\Assert;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class CheckoutContext extends ShopwareContext
{
    use CheckoutTestBehaviour;
    use PaymentMethodTestBehaviour;
    use MolliePageTestBehaviour;


    private string $mollieSandboxPage = '';
    private string $shopwareReturnPage = '';

    /**
     * @Given product :arg1 with quantity :arg2 is in cart
     */
    public function productWithQuantityIsInCart(string $productNumber, int $quantity): void
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $this->addItemToCart($productNumber, $salesChannelContext, $quantity);
    }

    /**
     * @When i start checkout with payment method :arg1
     */
    public function iStartCheckoutWithPaymentMethod(string $paymentMethodTechnicalName): void
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $paymentMethod = $this->getPaymentMethodByTechnicalName($paymentMethodTechnicalName, $salesChannelContext->getContext());
        $this->setOptions(SalesChannelContextService::PAYMENT_METHOD_ID, $paymentMethod->getId());

        /** @var RedirectResponse $response */
        $response = $this->startCheckout($this->getCurrentSalesChannelContext());

        $this->mollieSandboxPage = $response->getTargetUrl();
        Assert::assertStringContainsString('mollie.com', $this->mollieSandboxPage);
    }

    /**
     * @When select payment status :arg1
     */
    public function selectPaymentStatus(string $selectedStatus): void
    {
        $response = $this->selectMolliePaymentStatus($selectedStatus, $this->mollieSandboxPage);
        Assert::assertSame($response->getStatusCode(), 302);
        $this->shopwareReturnPage = $response->getHeaderLine('location');
        Assert::assertStringContainsString('mollie/payment', $this->shopwareReturnPage);
    }

    /**
     * @Given i select :art1 as currency
     */
    public function iSelectAsCurrency(string $currency): void
    {
        $currency = $this->findCurrencyByIso($currency,$this->getCurrentSalesChannelContext());
        $this->setOptions(SalesChannelContextService::CURRENCY_ID, $currency->getId());
    }

    /**
     * @Then i see success page
     */
    public function iSeeSuccessPage(): void
    {
        /** @var RedirectResponse $response */
        $response = $this->finishCheckout($this->shopwareReturnPage, $this->getCurrentSalesChannelContext());
        Assert::assertSame($response->getStatusCode(), 302);
        Assert::assertStringContainsString('/checkout/finish', $response->getTargetUrl());
    }
}