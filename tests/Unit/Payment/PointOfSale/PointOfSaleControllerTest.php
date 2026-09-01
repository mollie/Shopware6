<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\PointOfSale;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Payment\PointOfSale\PointOfSaleController;
use Mollie\Shopware\Component\Payment\PointOfSale\Route\StoreTerminalRoute;
use Mollie\Shopware\Unit\Fake\FakeRouter;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The terminal page polls the status route until the payment is decided. A successful payment goes
 * through Shopware's finalize; a failed one must not, because finalize would only raise a
 * PaymentException and strand the customer on the terminal page.
 */
#[CoversClass(PointOfSaleController::class)]
final class PointOfSaleControllerTest extends TestCase
{
    private const FINALIZE_URL = 'https://shop.test/payment/finalize?_sw_payment_token=tok_1';

    private FakeRouter $router;

    protected function setUp(): void
    {
        $this->router = new FakeRouter('https://shop.test/edit-order');
    }

    /**
     * While the customer is still at the terminal there is nowhere to send them yet. "success"
     * carries no meaning until "ready" is true - an open payment counts as approved, so the flag
     * is already true here and the front end must gate on "ready".
     */
    public function testAnOpenPaymentIsNotReadyYet(): void
    {
        $body = $this->checkStatus(PaymentStatus::OPEN);

        $this->assertFalse($body['ready']);
        $this->assertSame('', $body['redirectUrl']);
    }

    public function testAPaidTerminalPaymentIsSentThroughTheShopwareFinalize(): void
    {
        $body = $this->checkStatus(PaymentStatus::PAID);

        $this->assertTrue($body['ready']);
        $this->assertTrue($body['success']);
        $this->assertSame(self::FINALIZE_URL, $body['redirectUrl']);
    }

    /**
     * A failed payment would only raise a PaymentException in finalize, so the customer is sent
     * straight to the edit-order page instead.
     */
    public function testAFailedTerminalPaymentGoesToTheEditOrderPage(): void
    {
        $body = $this->checkStatus(PaymentStatus::FAILED);

        $this->assertTrue($body['ready']);
        $this->assertFalse($body['success']);
        $this->assertSame('https://shop.test/edit-order', $body['redirectUrl']);
        $this->assertSame('frontend.account.edit-order.page', $this->router->getLastRouteName());
        $this->assertSame(['orderId' => 'order-1'], $this->router->getLastParameters());
    }

    #[DataProvider('decidedButUnapprovedStatuses')]
    public function testAnUnapprovedButDecidedPaymentGoesToTheEditOrderPage(PaymentStatus $status): void
    {
        $body = $this->checkStatus($status);

        $this->assertTrue($body['ready']);
        $this->assertFalse($body['success']);
        $this->assertSame('frontend.account.edit-order.page', $this->router->getLastRouteName());
    }

    /**
     * @return array<string, array{PaymentStatus}>
     */
    public static function decidedButUnapprovedStatuses(): array
    {
        return [
            'cancelled at the terminal' => [PaymentStatus::CANCELED],
            'expired without an answer' => [PaymentStatus::EXPIRED],
        ];
    }

    /**
     * Terminal ids are no longer stored; the route only answers so old front ends keep working.
     */
    public function testStoringATerminalAnswersWithTheDeprecationNotice(): void
    {
        $response = $this->controller(new FakeGateway())
            ->storeTerminal('customer-1', 'term_1', new FakeSalesChannelContext())
        ;

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertTrue($body['success']);
        $this->assertStringContainsString('terminalId', $body['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkStatus(PaymentStatus $status): array
    {
        $gateway = new FakeGateway('', $this->payment($status));

        $response = $this->controller($gateway)
            ->checkStatus('transaction-1', 'tr_1', new FakeSalesChannelContext())
        ;

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function payment(PaymentStatus $status): Payment
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        $transaction->setOrderId('order-1');

        $payment = new Payment('tr_1');
        $payment->setStatus($status);
        $payment->setFinalizeUrl(self::FINALIZE_URL);
        $payment->setShopwareTransaction($transaction);

        return $payment;
    }

    private function controller(FakeGateway $gateway): PointOfSaleController
    {
        $controller = new PointOfSaleController(
            $gateway,
            new StoreTerminalRoute(new NullLogger()),
            new NullLogger()
        );

        $container = new Container();
        $container->set('router', $this->router);
        $container->set('event_dispatcher', new EventDispatcher());
        $container->set('request_stack', new RequestStack());

        $controller->setContainer($container);

        return $controller;
    }
}
