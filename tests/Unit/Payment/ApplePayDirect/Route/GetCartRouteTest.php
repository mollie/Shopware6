<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetCartRoute;
use Mollie\Shopware\Component\SalesChannel\LocaleProvider;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Unit\Builder\LineItemFilterBuilder;
use Mollie\Shopware\Unit\Fake\FakeLanguageRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Fake\FakeShopwareTranslator;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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
use Symfony\Component\HttpFoundation\Request;

/**
 * The Apple Pay sheet is rendered by the browser from exactly what this route answers. A wrong
 * label or a wrong amount is not a cosmetic bug - the shopper approves the total they see here,
 * and Apple rejects a sheet whose line items do not add up.
 */
#[CoversClass(GetCartRoute::class)]
final class GetCartRouteTest extends TestCase
{
    private const SUBTOTAL_SNIPPET = 'molliePayments.payments.applePayDirect.captionSubtotal';
    private const TAX_SNIPPET = 'molliePayments.payments.applePayDirect.captionTaxes';
    private const TEST_MODE_SNIPPET = 'molliePayments.testMode.label';

    public function testEveryCartLineItemBecomesAnApplePayLineItem(): void
    {
        $cart = $this->cart([
            $this->lineItem('line-1', 'Product A', 20.0),
            $this->lineItem('line-2', 'Product B', 5.0),
        ]);

        $items = $this->applePayCart($cart)['items'];

        $this->assertSame('Product A', $items[0]['label']);
        $this->assertSame(20.0, (float) $items[0]['amount']);
        $this->assertSame('Product B', $items[1]['label']);
        $this->assertSame(5.0, (float) $items[1]['amount']);
    }

    /**
     * Apple only accepts "final" or "pending"; anything else makes the sheet fail to open.
     */
    public function testEveryLineItemIsMarkedAsFinal(): void
    {
        $items = $this->applePayCart($this->cart([$this->lineItem('line-1', 'Product A', 20.0)]))['items'];

        foreach ($items as $item) {
            $this->assertSame('final', $item['type']);
        }
    }

    /**
     * The subtotal is the sum of the products, without shipping and without tax.
     */
    public function testTheSubtotalIsTheSumOfTheProducts(): void
    {
        $cart = $this->cart([
            $this->lineItem('line-1', 'Product A', 20.0),
            $this->lineItem('line-2', 'Product B', 5.0),
        ]);

        $subtotal = $this->itemByLabel($this->applePayCart($cart), 'Subtotal');

        $this->assertSame(25.0, (float) $subtotal['amount']);
    }

    public function testTheShippingCostsAreTheirOwnLineItem(): void
    {
        $cart = $this->cart([$this->lineItem('line-1', 'Product A', 20.0)], shippingCosts: 4.99);

        $shipping = $this->itemByLabel($this->applePayCart($cart), 'Standard delivery');

        $this->assertSame(4.99, (float) $shipping['amount']);
    }

    /**
     * The taxes of the products and of the shipping end up in one line, as Apple shows a single
     * tax row.
     */
    public function testTheTaxesOfProductsAndShippingAreSummedIntoOneLine(): void
    {
        $cart = $this->cart([$this->lineItem('line-1', 'Product A', 20.0, tax: 3.19)], shippingCosts: 4.99, shippingTax: 0.8);

        $taxes = $this->itemByLabel($this->applePayCart($cart), 'Taxes');

        $this->assertSame(3.99, (float) $taxes['amount']);
    }

    /**
     * A net shop has no tax to show, and an empty tax row of 0.00 confuses the shopper.
     */
    public function testAnOrderWithoutTaxHasNoTaxLine(): void
    {
        $cart = $this->cart([$this->lineItem('line-1', 'Product A', 20.0)]);

        $labels = array_column($this->applePayCart($cart)['items'], 'label');

        $this->assertNotContains('Taxes', $labels);
    }

    /**
     * The total is what the shopper approves, so it comes from the cart's own total, not from
     * adding the line items up again.
     */
    public function testTheTotalIsTheCartTotal(): void
    {
        $cart = $this->cart([$this->lineItem('line-1', 'Product A', 20.0)], total: 24.99);

        $this->assertSame(24.99, (float) $this->applePayCart($cart)['total']['amount']);
    }

    public function testTheShopNameIsWhatTheSheetIsLabelledWith(): void
    {
        $applePayCart = $this->applePayCart($this->cart([]));

        $this->assertSame('Fake Sales Channel', $applePayCart['label']);
        $this->assertSame('Fake Sales Channel', $applePayCart['total']['label']);
    }

    /**
     * A test mode payment charges nothing, and the shopper has to see that in the sheet.
     */
    public function testATestModeShopSaysSoInTheSheetLabel(): void
    {
        $applePayCart = $this->applePayCart($this->cart([]), testMode: true);

        $this->assertSame('Fake Sales Channel (Test mode)', $applePayCart['label']);
    }

    /**
     * The snippets have to be read in the language of the sales channel the shopper is in, not in
     * whatever language the store-api request happens to run under.
     */
    public function testTheCaptionsAreReadInTheSalesChannelsLanguage(): void
    {
        $translator = new FakeShopwareTranslator();

        $this->applePayCart($this->cart([]), translator: $translator);

        $this->assertSame(['de-DE'], array_unique($translator->getInjectedLocales()));
    }

    /**
     * The captions are snippets, so a Dutch shop does not get English rows.
     */
    public function testTheCaptionsComeFromTheSnippetFiles(): void
    {
        $translator = new FakeShopwareTranslator([
            self::SUBTOTAL_SNIPPET => 'Zwischensumme',
            self::TAX_SNIPPET => 'Steuern',
        ]);

        $cart = $this->cart([$this->lineItem('line-1', 'Product A', 20.0, tax: 3.19)]);
        $labels = array_column($this->applePayCart($cart, translator: $translator)['items'], 'label');

        $this->assertContains('Zwischensumme', $labels);
        $this->assertContains('Steuern', $labels);
    }

    /**
     * A shop that has not translated the captions must not end up with an empty row label.
     */
    public function testAnUntranslatedCaptionFallsBackToEnglish(): void
    {
        $translator = new FakeShopwareTranslator([
            self::SUBTOTAL_SNIPPET => '',
            self::TAX_SNIPPET => '',
            self::TEST_MODE_SNIPPET => '',
        ]);

        $cart = $this->cart([$this->lineItem('line-1', 'Product A', 20.0, tax: 3.19)]);
        $applePayCart = $this->applePayCart($cart, testMode: true, translator: $translator);
        $labels = array_column($applePayCart['items'], 'label');

        $this->assertContains('Subtotal', $labels);
        $this->assertContains('Taxes', $labels);
        $this->assertSame('Fake Sales Channel (Test mode)', $applePayCart['label']);
    }

    /**
     * The shipping row is the one the shipping-method route reads the costs back from, so it has
     * to be recognizable as a shipping line and not just another item.
     */
    public function testTheShippingCostsCanBeReadBackFromTheCart(): void
    {
        $cart = $this->cart([$this->lineItem('line-1', 'Product A', 20.0)], shippingCosts: 4.99);

        $response = $this->route($cart)->cart(new Request(), new FakeSalesChannelContext());

        $this->assertSame(4.99, $response->getCart()->getShippingAmount()->getValue());
        $this->assertSame($cart, $response->getShopwareCart());
    }

    public function testTheShopwareCartIsHandedBackForTheFollowUpRoutes(): void
    {
        $cart = $this->cart([$this->lineItem('line-1', 'Product A', 20.0)]);

        $response = $this->route($cart)->cart(new Request(), new FakeSalesChannelContext());

        $this->assertSame($cart, $response->getShopwareCart());
    }

    /**
     * The sheet is rendered from the encoded response, so the assertions read the encoded shape.
     * Amounts are cast on the way out: json_encode drops the zero fraction, so a whole 20.00 comes
     * back as an int - harmless for the browser, but assertSame() would trip over it.
     *
     * @return array<string, mixed>
     */
    private function applePayCart(Cart $cart, bool $testMode = false, ?FakeShopwareTranslator $translator = null): array
    {
        $response = $this->route($cart, $testMode, $translator)->cart(new Request(), new FakeSalesChannelContext());

        return json_decode((string) json_encode($response->getCart()), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $applePayCart
     *
     * @return array<string, mixed>
     */
    private function itemByLabel(array $applePayCart, string $label): array
    {
        foreach ($applePayCart['items'] as $item) {
            if ($item['label'] === $label) {
                return $item;
            }
        }

        $this->fail(sprintf('The Apple Pay cart has no line item labelled "%s".', $label));
    }

    private function route(?Cart $cart = null, bool $testMode = false, ?FakeShopwareTranslator $translator = null): GetCartRoute
    {
        return new GetCartRoute(
            new FakeCartService($cart ?? $this->cart([])),
            new FakeSettingsService(apiSettings: new ApiSettings('test_key', 'live_key', $testMode ? Mode::TEST : Mode::LIVE, 'pfl_1')),
            $translator ?? new FakeShopwareTranslator([
                self::SUBTOTAL_SNIPPET => 'Subtotal',
                self::TAX_SNIPPET => 'Taxes',
                self::TEST_MODE_SNIPPET => 'Test mode',
            ]),
            new LocaleProvider(new FakeLanguageRepository('de-DE')),
            LineItemFilterBuilder::build(),
            new NullLogger()
        );
    }

    /**
     * @param list<LineItem> $lineItems
     */
    private function cart(array $lineItems, float $shippingCosts = 0.0, float $shippingTax = 0.0, ?float $total = null): Cart
    {
        $cart = new Cart('apple-pay-token');
        $cart->setLineItems(new LineItemCollection($lineItems));

        $totalPrice = $total ?? array_sum(array_map(fn (LineItem $item) => $item->getPrice()?->getTotalPrice() ?? 0.0, $lineItems));
        $cart->setPrice(new CartPrice(
            $totalPrice,
            $totalPrice,
            $totalPrice,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));

        if ($shippingCosts > 0.0) {
            $cart->setDeliveries(new DeliveryCollection([$this->delivery($shippingCosts, $shippingTax)]));
        }

        return $cart;
    }

    private function delivery(float $shippingCosts, float $shippingTax): Delivery
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('shipping-method-1');
        $shippingMethod->setName('Standard delivery');

        $country = new CountryEntity();
        $country->setId('country-de');

        $taxes = new CalculatedTaxCollection();
        if ($shippingTax > 0.0) {
            $taxes->add(new CalculatedTax($shippingTax, 19.0, $shippingCosts));
        }

        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-03')),
            $shippingMethod,
            ShippingLocation::createFromCountry($country),
            new CalculatedPrice($shippingCosts, $shippingCosts, $taxes, new TaxRuleCollection())
        );
    }

    private function lineItem(string $id, string $label, float $totalPrice, float $tax = 0.0): LineItem
    {
        $taxes = new CalculatedTaxCollection();
        if ($tax > 0.0) {
            $taxes->add(new CalculatedTax($tax, 19.0, $totalPrice));
        }

        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, $id);
        $lineItem->setLabel($label);
        $lineItem->setPrice(new CalculatedPrice($totalPrice, $totalPrice, $taxes, new TaxRuleCollection()));

        return $lineItem;
    }
}
