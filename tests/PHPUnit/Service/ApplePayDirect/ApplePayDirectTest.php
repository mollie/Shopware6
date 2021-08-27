<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service\ApplePayDirect;

use Kiener\MolliePayments\Service\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Service\ApplePayDirect\ApplePayDomainVerificationService;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\ShippingMethodService;
use MolliePayments\Tests\Fakes\FakeTranslator;
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
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Symfony\Component\Validator\Constraints\Country;

class ApplePayDirectTest extends TestCase
{

    use MockTrait;

    /**
     * This test verifies that our Apple Pay Cart is correctly
     * built from a provided Shopware Cart object.
     */
    public function testBuildApplePayCart(): void
    {
        /** @var CartService $cartService */
        $cartService = $this->createDummyMock(CartService::class, $this);
        /** @var ShippingMethodService $shippingMethodService */
        $shippingMethodService = $this->createDummyMock(ShippingMethodService::class, $this);

        $applePay = new ApplePayDirect($cartService, $shippingMethodService, new FakeTranslator());


        $swCart = $this->buildShopwareCart();

        $apCart = $applePay->buildApplePayCart($swCart);

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
