<?php

namespace Kiener\MolliePayments\Tests\Service\ApplePayDirect\Models;

use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayCart;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayFormatter;
use Kiener\MolliePayments\Service\Router\RoutingDetector;
use MolliePayments\Tests\Fakes\FakeTranslator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;


class ApplePayFormatterTest extends TestCase
{

    /**
     * @var ApplePayFormatter
     */
    private $formatter;


    /**
     *
     */
    public function setUp(): void
    {
        $fakeSnippets = new FakeTranslator();
        $fakeSnippets->addSnippet('molliePayments.testMode.label', 'Test Mode');
        $fakeSnippets->addSnippet('molliePayments.payments.applePayDirect.captionSubtotal', 'Subtotal');
        $fakeSnippets->addSnippet('molliePayments.payments.applePayDirect.captionTaxes', 'Taxes');

        $routingDetector = new RoutingDetector(new RequestStack(new Request()));
        $this->formatter = new ApplePayFormatter($fakeSnippets, $routingDetector);
    }

    /**
     * This test verifies that our cart object is converted
     * into the correct array that will be sent to the
     * Apple Pay Javascript framework.
     * These are the final values which will be displayed in the
     * Apple Pay Payment Sheet.
     */
    public function testFormatCart()
    {
        $cart = $this->getSampleCart();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setName('My Shop');

        $formattedCart = $this->formatter->formatCart($cart, $salesChannel, false);

        $expected = [
            'label' => 'My Shop',
            'amount' => 61.0,
            'items' => [
                [
                    'label' => 'Subtotal',
                    'type' => 'final',
                    'amount' => 55.0,
                ],
                [
                    'label' => 'Express',
                    'type' => 'final',
                    'amount' => 5.0,
                ],
                [
                    'label' => 'Over-Night',
                    'type' => 'final',
                    'amount' => 1.0,
                ],
                [
                    'label' => 'Taxes',
                    'type' => 'final',
                    'amount' => 11.0,
                ],
            ],
            'total' => [
                'label' => 'My Shop',
                'type' => 'final',
                'amount' => 61.0,
            ]
        ];

        $this->assertEquals($expected, $formattedCart);
    }

    /**
     * This test verifies that our formatter automatically
     * adds the snippet for test mode to the shop name,
     * if formatted in test mode.
     */
    public function testTestModeIndicator()
    {
        $cart = $this->getSampleCart();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setName('My Shop');

        $formattedCart = $this->formatter->formatCart($cart, $salesChannel, true);

        $this->assertEquals('My Shop (Test Mode)', $formattedCart['label']);
        $this->assertEquals('My Shop (Test Mode)', $formattedCart['total']['label']);
    }

    /**
     * @return ApplePayCart
     */
    private function getSampleCart(): ApplePayCart
    {
        $cart = new ApplePayCart();
        $cart->addItem('123', 'T-Shirt', 3, 10);
        $cart->addItem('333', 'Pants', 1, 25);
        $cart->addShipping('Express', 5);
        $cart->addShipping('Over-Night', 1);
        $cart->setTaxes(11);

        return $cart;
    }
}
