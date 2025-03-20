<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\PaypalExpress;

use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Service\CartServiceInterface;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Resources\Session;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

trait PayPalExpressMockTrait
{
    private ?Cart $cart = null;

    public function tearDown(): void
    {
        $this->cart = null;
    }

    private function getSettings(bool $paymentEnabled = false, bool $requiredDataProtection = false): SettingsService
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->createMock(SettingsService::class);
        $settings = new MollieSettingStruct();
        if ($paymentEnabled) {
            $settings->setPaypalExpressEnabled(true);
        }
        if ($requiredDataProtection) {
            $settings->setRequireDataProtectionCheckbox(true);
        }
        $settingsService->method('getSettings')->willReturn($settings);

        return $settingsService;
    }

    private function getCartService(bool $withCart = false, bool $withLineItems = false, bool $withFakeSessionId = false): CartServiceInterface
    {
        /** @var CartServiceInterface $cartService */
        $cartService = $this->createMock(CartServiceInterface::class);

        if ($withCart) {
            $fakeCart = new Cart('fake', 'fake');

            if ($withLineItems) {
                $fakeLineItem = $this->createMock(LineItem::class);
                $lineItemsCollection = new LineItemCollection([$fakeLineItem]);

                $fakeCart->setLineItems($lineItemsCollection);
            }

            if ($withFakeSessionId) {
                $fakeCart->addExtensions([
                    CustomFieldsInterface::MOLLIE_KEY => new ArrayStruct([
                        CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY => 'fakeLoadedSessionId',
                    ]),
                ]);
            }

            $this->cart = $fakeCart;
            $cartService->expects($this->once())->method('getCalculatedMainCart')->willReturn($fakeCart);
        }

        return $cartService;
    }

    private function getPaypalExpress(bool $withSessionId = false, bool $withStartSession = false, bool $withLoadSession = false, bool $withRedirectUrl = false, bool $withAuthenticateId = false, ?\stdClass $methodDetails = null): PayPalExpress
    {
        if ($methodDetails === null) {
            $methodDetails = new \stdClass();
        }
        /** @var PayPalExpress $paypalExpress */
        $paypalExpress = $this->createMock(PayPalExpress::class);
        $fakeSession = $this->createMock(Session::class);

        if ($withSessionId) {
            $fakeSession->id = 'fakeSessionId';
            if ($withRedirectUrl) {
                $fakeSession->redirectUrl = 'fakeRedirectUrl';
            }
            if ($withAuthenticateId) {
                $fakeSession->authenticationId = 'fakeAuthenticationId';
            }
            $fakeSession->methodDetails = $methodDetails;
        }

        if ($withStartSession) {
            $paypalExpress->expects($this->once())->method('startSession')->willReturn($fakeSession);
        }
        if ($withLoadSession) {
            $paypalExpress->expects($this->once())->method('loadSession')->willReturn($fakeSession);
        }

        return $paypalExpress;
    }

    private function getContext(): SalesChannelContext
    {
        /**
         * @var SalesChannelContext $context
         */
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('fakeSalesChannelId');

        return $context;
    }
}
