<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\MethodRemover;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\PaymentLinkGateway;
use Mollie\Shopware\Component\Payment\MethodRemover\AvailabilityPaymentMethodRemover;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\PaymentMethodBuilder;
use Mollie\Shopware\Unit\Fake\FakeOrderRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartService;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionAwarePaymentHandler;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;

#[CoversClass(AvailabilityPaymentMethodRemover::class)]
final class AvailabilityPaymentMethodRemoverTest extends TestCase
{
    public function testReturnsAllMethodsWhenLimitsDisabled(): void
    {
        $remover = $this->getRemover(useLimits: false, activeMethodIds: [], cart: $this->buildCart(100.0));

        $result = $remover->remove($this->buildPaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertCount(3, $result);
    }

    public function testRemovesUnavailableMollieMethods(): void
    {
        $remover = $this->getRemover(useLimits: true, activeMethodIds: ['paypal'], cart: $this->buildCart(100.0));

        $result = $remover->remove($this->buildPaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertCount(2, $result);
        $this->assertNotNull($result->get('paypal-id'), 'available mollie method is kept');
        $this->assertNull($result->get('creditcard-id'), 'unavailable mollie method is removed');
        $this->assertNotNull($result->get('non-mollie-id'), 'non mollie method is always kept');
    }

    public function testReturnsAllMethodsWhenCartAmountIsZero(): void
    {
        $remover = $this->getRemover(useLimits: true, activeMethodIds: ['paypal'], cart: $this->buildCart(0.0));

        $result = $remover->remove($this->buildPaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertCount(3, $result);
    }

    /**
     * @param string[] $activeMethodIds
     */
    private function getRemover(bool $useLimits, array $activeMethodIds, Cart $cart): AvailabilityPaymentMethodRemover
    {
        $methods = array_map(function (string $id): array {
            return ['id' => $id];
        }, $activeMethodIds);

        $mockHandler = new MockHandler([
            new Response(200, [], (string) json_encode(['_embedded' => ['methods' => $methods]])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        $gateway = new MollieGateway(new FakeClientFactory($client), new FakeTransactionService(), new PaymentLinkGateway(new FakeClientFactory($client), new NullLogger()), new NullLogger());

        $handlerLocator = new PaymentHandlerLocator([
            new FakePaymentMethodHandler(),
            new FakeSubscriptionAwarePaymentHandler(),
        ]);

        $settingsService = new FakeSettingsService(paymentSettings: new PaymentSettings('', 0, useMollieLimits: $useLimits));

        return new AvailabilityPaymentMethodRemover(
            $handlerLocator,
            $gateway,
            new FakeCartService($cart),
            new FakeOrderRepository(),
            $settingsService
        );
    }

    private function buildPaymentMethods(): PaymentMethodCollection
    {
        $paypal = PaymentMethodBuilder::create()
            ->withId('paypal-id')
            ->withHandlerIdentifier(FakePaymentMethodHandler::class)
            ->build()
        ;

        $creditcard = PaymentMethodBuilder::create()
            ->withId('creditcard-id')
            ->withHandlerIdentifier(FakeSubscriptionAwarePaymentHandler::class)
            ->build()
        ;

        $nonMollie = PaymentMethodBuilder::create()
            ->withId('non-mollie-id')
            ->withHandlerIdentifier('Some\Other\PaymentHandler')
            ->build()
        ;

        return new PaymentMethodCollection([$paypal, $creditcard, $nonMollie]);
    }

    private function buildCart(float $totalPrice): Cart
    {
        $cart = CartBuilder::create()->build();
        $cart->setPrice(new CartPrice(
            $totalPrice,
            $totalPrice,
            $totalPrice,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));

        return $cart;
    }
}
