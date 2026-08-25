<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\PayPalExpress;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Payment\Method\PayPalExpressPayment;
use Mollie\Shopware\Component\Payment\PayPalExpress\PaypalExpressMethodRemover;
use Mollie\Shopware\Component\Settings\Struct\PayPalExpressSettings;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\PaymentMethodBuilder;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;

#[CoversClass(PaypalExpressMethodRemover::class)]
final class PaypalExpressMethodRemoverTest extends TestCase
{
    private const ORDER_ID = 'order-id';
    private const AUTHENTICATION_ID = 'auth_123';

    public function testCollectionWithoutPaypalExpressIsReturnedUnchanged(): void
    {
        $paymentMethods = new PaymentMethodCollection([
            PaymentMethodBuilder::create()->withId('paypal-id')->withHandlerIdentifier('Some\Other\PaymentHandler')->build(),
        ]);

        $result = $this->makeRemover(CartBuilder::create()->build())->remove($paymentMethods, '', new FakeSalesChannelContext());

        $this->assertCount(1, $result);
    }

    public function testPaypalExpressIsRemovedWhileTheFeatureIsDisabled(): void
    {
        $result = $this->makeRemover($this->cartWithSession(), enabled: false)
            ->remove($this->makePaymentMethods(), '', new FakeSalesChannelContext())
        ;

        $this->assertNull($result->get('paypal-express-id'));
        $this->assertNotNull($result->get('paypal-id'));
    }

    public function testPaypalExpressIsRemovedForACartWithoutAMollieSession(): void
    {
        $result = $this->makeRemover(CartBuilder::create()->build())
            ->remove($this->makePaymentMethods(), '', new FakeSalesChannelContext())
        ;

        $this->assertNull($result->get('paypal-express-id'));
    }

    public function testCartWithAMollieSessionLeavesOnlyPaypalExpress(): void
    {
        $result = $this->makeRemover($this->cartWithSession())
            ->remove($this->makePaymentMethods(), '', new FakeSalesChannelContext())
        ;

        $this->assertCount(1, $result);
        $this->assertNotNull($result->get('paypal-express-id'));
    }

    public function testAuthenticationIdOfTheSessionIsHandedToTheStorefront(): void
    {
        $result = $this->makeRemover($this->cartWithSession())
            ->remove($this->makePaymentMethods(), '', new FakeSalesChannelContext())
        ;

        $customFields = $result->get('paypal-express-id')->getCustomFields();

        $this->assertSame(['authenticationId' => self::AUTHENTICATION_ID], $customFields[Mollie::EXTENSION]);
    }

    public function testPaypalExpressIsRemovedWhenTheOrderCannotBeLoaded(): void
    {
        $result = $this->makeRemover(CartBuilder::create()->build(), orderRepository: new FakeOrderSearchRepository())
            ->remove($this->makePaymentMethods(), self::ORDER_ID, new FakeSalesChannelContext())
        ;

        $this->assertNull($result->get('paypal-express-id'));
    }

    public function testPaypalExpressIsRemovedForAnOrderWithoutTransactions(): void
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);

        $result = $this->makeRemover(CartBuilder::create()->build(), orderRepository: $this->orderRepositoryWith($order))
            ->remove($this->makePaymentMethods(), self::ORDER_ID, new FakeSalesChannelContext())
        ;

        $this->assertNull($result->get('paypal-express-id'));
    }

    public function testPaypalExpressIsRemovedForATransactionWithoutMolliePayment(): void
    {
        $order = $this->orderWithTransaction(new OrderTransactionEntity());

        $result = $this->makeRemover(CartBuilder::create()->build(), orderRepository: $this->orderRepositoryWith($order))
            ->remove($this->makePaymentMethods(), self::ORDER_ID, new FakeSalesChannelContext())
        ;

        $this->assertNull($result->get('paypal-express-id'));
    }

    public function testPaypalExpressIsRemovedWhenThePaymentHasNoAuthenticationId(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->addExtension(Mollie::EXTENSION, new Payment('tr_test'));

        $result = $this->makeRemover(CartBuilder::create()->build(), orderRepository: $this->orderRepositoryWith($this->orderWithTransaction($transaction)))
            ->remove($this->makePaymentMethods(), self::ORDER_ID, new FakeSalesChannelContext())
        ;

        $this->assertNull($result->get('paypal-express-id'));
    }

    public function testOrderPaymentAuthenticationIdIsHandedToTheStorefront(): void
    {
        $payment = new Payment('tr_test');
        $payment->setAuthenticationId(self::AUTHENTICATION_ID);
        $transaction = new OrderTransactionEntity();
        $transaction->addExtension(Mollie::EXTENSION, $payment);

        $result = $this->makeRemover(CartBuilder::create()->build(), orderRepository: $this->orderRepositoryWith($this->orderWithTransaction($transaction)))
            ->remove($this->makePaymentMethods(), self::ORDER_ID, new FakeSalesChannelContext())
        ;

        $customFields = $result->get('paypal-express-id')->getCustomFields();

        $this->assertCount(1, $result);
        $this->assertSame(['authenticationId' => self::AUTHENTICATION_ID], $customFields[Mollie::EXTENSION]);
    }

    private function makeRemover(Cart $cart, bool $enabled = true, ?FakeOrderSearchRepository $orderRepository = null): PaypalExpressMethodRemover
    {
        return new PaypalExpressMethodRemover(
            new FakeCartService($cart),
            $orderRepository ?? new FakeOrderSearchRepository(),
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings($enabled))
        );
    }

    private function makePaymentMethods(): PaymentMethodCollection
    {
        return new PaymentMethodCollection([
            PaymentMethodBuilder::create()->withId('paypal-express-id')->withHandlerIdentifier(PayPalExpressPayment::class)->build(),
            PaymentMethodBuilder::create()->withId('paypal-id')->withHandlerIdentifier('Some\Other\PaymentHandler')->build(),
        ]);
    }

    private function cartWithSession(): Cart
    {
        $session = new Session('ses_test');
        $session->setAuthenticationId(self::AUTHENTICATION_ID);

        $cart = CartBuilder::create()->build();
        $cart->addExtension(Mollie::EXTENSION, $session);

        return $cart;
    }

    private function orderWithTransaction(OrderTransactionEntity $transaction): OrderEntity
    {
        $transaction->setId('order-transaction-id');

        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        return $order;
    }

    private function orderRepositoryWith(OrderEntity $order): FakeOrderSearchRepository
    {
        $repository = new FakeOrderSearchRepository();
        $repository->add($order);

        return $repository;
    }
}
