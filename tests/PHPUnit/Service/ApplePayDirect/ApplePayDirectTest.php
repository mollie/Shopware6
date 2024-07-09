<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service\ApplePayDirect;

use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Components\ApplePayDirect\Exceptions\ApplePayValidationUrlAllowListCanNotBeEmptyException;
use Kiener\MolliePayments\Components\ApplePayDirect\Exceptions\ApplePayValidationUrlNotInAllowListException;
use Kiener\MolliePayments\Components\ApplePayDirect\Gateways\ApplePayValidationUrlAllowListGateway;
use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayValidationUrlAllowListItem;
use Kiener\MolliePayments\Components\ApplePayDirect\Models\Collections\ApplePayValidationUrlAllowList;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayDomainVerificationService;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayFormatter;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayShippingBuilder;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayValidationUrlSanitizer;
use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Repository\Order\OrderAddressRepository;
use Kiener\MolliePayments\Repository\PaymentMethod\PaymentMethodRepository;
use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\ShippingMethodService;
use Kiener\MolliePayments\Service\ShopService;
use MolliePayments\Tests\Fakes\FakeCartService;
use MolliePayments\Tests\Traits\MockTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ApplePayDirectTest extends TestCase
{
    use MockTrait;

    private SalesChannelContext $scContext;

    private $validationUrlAllowListGateway;
    private ApplePayDirect $applePay;

    protected function setUp(): void
    {
        $swCart = $this->buildShopwareCart();

        $this->scContext = $this->createDummyMock(SalesChannelContext::class, $this);

        $fakeCartService = new FakeCartService($swCart, $this->scContext);

        /** @var ApplePayDomainVerificationService $domainVerification */
        $domainVerification = $this->createDummyMock(ApplePayDomainVerificationService::class, $this);

        /** @var ApplePayPayment $payment */
        $payment = $this->createDummyMock(ApplePayPayment::class, $this);

        /** @var MolliePaymentDoPay $doPay */
        $doPay = $this->createDummyMock(MolliePaymentDoPay::class, $this);

        /** @var ApplePayFormatter $formatter */
        $formatter = $this->createDummyMock(ApplePayFormatter::class, $this);

        /** @var ApplePayShippingBuilder $shippingBuilder */
        $shippingBuilder = $this->createDummyMock(ApplePayShippingBuilder::class, $this);

        /** @var SettingsService $settingsService */
        $settingsService = $this->createDummyMock(SettingsService::class, $this);

        /** @var CustomerService $customerService */
        $customerService = $this->createDummyMock(CustomerService::class, $this);

        /** @var PaymentMethodRepository $repoPaymentMethods */
        $repoPaymentMethods = $this->createDummyMock(PaymentMethodRepository::class, $this);

        /** @var CartBackupService $cartBackupService */
        $cartBackupService = $this->createDummyMock(CartBackupService::class, $this);

        /** @var MollieApiFactory $apiFactory */
        $apiFactory = $this->createDummyMock(MollieApiFactory::class, $this);

        /** @var ShopService $shopService */
        $shopService = $this->createDummyMock(ShopService::class, $this);

        /** @var OrderService $orderService */
        $orderService = $this->createDummyMock(OrderService::class, $this);

        /** @var OrderAddressRepository $repoOrderAdresses */
        $repoOrderAdresses = $this->createDummyMock(OrderAddressRepository::class, $this);

        $this->validationUrlAllowListGateway = $this->createDummyMock(ApplePayValidationUrlAllowListGateway::class, $this);

        $validationUrlSanitizer = new ApplePayValidationUrlSanitizer();

        $this->applePay = new ApplePayDirect(
            $domainVerification,
            $payment,
            $doPay,
            $fakeCartService,
            $formatter,
            $shippingBuilder,
            $settingsService,
            $customerService,
            $repoPaymentMethods,
            $cartBackupService,
            $apiFactory,
            $shopService,
            $orderService,
            $repoOrderAdresses,
            $this->validationUrlAllowListGateway,
            $validationUrlSanitizer
        );
    }

    /**
     * This test verifies that our Apple Pay Cart is correctly
     * built from a provided Shopware Cart object.
     */
    public function testBuildApplePayCart(): void
    {
        $apCart = $this->applePay->getCart($this->scContext);

        $this->assertEquals(34.99, $apCart->getAmount());
        $this->assertEquals(5, $apCart->getTaxes()->getPrice());

        $this->assertEquals('ref-123', $apCart->getItems()[0]->getNumber());
        $this->assertEquals('T-Shirt', $apCart->getItems()[0]->getName());
        $this->assertEquals(10, $apCart->getItems()[0]->getPrice());
        $this->assertEquals(3, $apCart->getItems()[0]->getQuantity());

        $this->assertEquals('SHIPPING', $apCart->getShippings()[0]->getNumber());
        $this->assertEquals('Express', $apCart->getShippings()[0]->getName());
        $this->assertEquals(4.99, $apCart->getShippings()[0]->getPrice());
        $this->assertEquals(1, $apCart->getShippings()[0]->getQuantity());
    }

    public function testThrowsExceptionWhenAllowListIsEmpty(): void
    {
        $this->validationUrlAllowListGateway->expects($this->once())
            ->method('getAllowList')
            ->willReturn(ApplePayValidationUrlAllowList::create());

        $this->expectException(ApplePayValidationUrlAllowListCanNotBeEmptyException::class);
        $this->expectExceptionMessage('The Apple Pay validation URL allow list can not be empty. Please check the configuration.');

        $this->applePay->validateValidationUrl('https://example.com');
    }

    public function testThrowsExceptionWhenUrlIsNotInAllowList(): void
    {
        $allowList = ApplePayValidationUrlAllowList::create(
            ApplePayValidationUrlAllowListItem::create('https://example.com/')
        );
        $this->validationUrlAllowListGateway->expects($this->once())
            ->method('getAllowList')
            ->willReturn($allowList);

        $testUrl = 'https://example.org/';

        $this->expectException(ApplePayValidationUrlNotInAllowListException::class);
        $this->expectExceptionMessage(sprintf('The given URL %s is not in the Apple Pay validation URL allow list.', $testUrl));

        $this->applePay->validateValidationUrl($testUrl);
    }

    public function testProvidesValidValidationUrl(): void
    {
        $allowList = ApplePayValidationUrlAllowList::create(
            ApplePayValidationUrlAllowListItem::create($expected = 'https://example.com/')
        );

        $this->validationUrlAllowListGateway->expects($this->once())
            ->method('getAllowList')
            ->willReturn($allowList);

        $actual = $this->applePay->validateValidationUrl($expected);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return Cart
     */
    private function buildShopwareCart(): Cart
    {
        $unitPrice = 10;
        $quantity = 3;
        $taxValue = 5;
        $productTotal = $quantity * $unitPrice;

        $shippingCosts = 4.99;

        $cartTotal = $productTotal + $shippingCosts;


        $swCart = new Cart('Dummy Cart', 'dummy');

        # creat our fake calculated taxes
        # we only need the tax value in here
        $taxes = new CalculatedTaxCollection([new CalculatedTax($taxValue, 0, 0)]);

        # now create a real price with real calculation
        # for our product
        $price = new CalculatedPrice($unitPrice, $unitPrice * $quantity, $taxes, new TaxRuleCollection());

        # create our product and assign
        # our price to it
        $product = new LineItem('123', 'product', 'ref-123', $quantity);
        $product->setLabel('T-Shirt');
        $product->setPrice($price);

        # add the product to our cart
        $swCart->setLineItems(new LineItemCollection([$product]));

        # add a delivery
        # create a price and add it to our cart
        $deliveryPrice = new CalculatedPrice($shippingCosts, $shippingCosts, new CalculatedTaxCollection(), new TaxRuleCollection());

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setName('Express');

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTime(), new \DateTime()),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            $deliveryPrice
        );

        $swCart->setDeliveries(new DeliveryCollection([$delivery]));

        # manually fake a calculation and assign
        # the correct price values for our cart
        $swCart->setPrice(new CartPrice($cartTotal, $cartTotal, $cartTotal, $taxes, new TaxRuleCollection(), ''));

        return $swCart;
    }
}
