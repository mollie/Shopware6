<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Action;

use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Component\Payment\CreatePaymentBuilder;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Unit\Fake\FakeEventDispatcher;
use Mollie\Shopware\Unit\Logger\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeCustomerRepository;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Payment\Fake\FakeOrderTransactionStateHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[CoversClass(Pay::class)]
final class PayTest extends TestCase
{
    public function testPayActionRedirectToMollieCheckoutUrl(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();
        $expectedUrl = 'https://mollie.com/checkout=token=123';

        $payAction = $this->getPayAction($transactionService, $expectedUrl);

        $response = $payAction->execute(new FakePaymentMethodHandler(), new PaymentTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($expectedUrl, $response->getTargetUrl());
    }

    public function testPayActionRedirectToShopwareReturnUrl(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();
        $expectedUrl = 'returnUrl';

        $payAction = $this->getPayAction($transactionService, $expectedUrl);

        $response = $payAction->execute(new FakePaymentMethodHandler(), new PaymentTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($expectedUrl, $response->getTargetUrl());
    }

    private function getPayAction(FakeTransactionService $transactionService, string $checkoutUrl): Pay
    {
        $eventDispatcher = new FakeEventDispatcher();
        $fakeRouteBuilder = new FakeRouteBuilder();
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings);

        $fakeOrderTransactionStateHandler = new FakeOrderTransactionStateHandler();
        $fakeCustomerRepository = new FakeCustomerRepository();
        $logger = new NullLogger();
        $gateway = new FakeGateway($checkoutUrl);
        $builder = new CreatePaymentBuilder($fakeRouteBuilder, $settingsService,$gateway,$fakeCustomerRepository,$logger);

        return new Pay($transactionService, $builder, $gateway, $fakeOrderTransactionStateHandler, $fakeRouteBuilder,$eventDispatcher, $logger);
    }
}
