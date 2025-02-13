<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\PaypalExpress\Route;

use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Components\PaypalExpress\PaypalExpressException;
use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Kiener\MolliePayments\Service\CartServiceInterface;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @final
 */
class CancelCheckoutRoute extends AbstractCancelCheckoutRoute
{
    private SettingsService $settingsService;
    private CartBackupService $cartBackupService;
    private PayPalExpress $paypalExpress;
    private CartServiceInterface $cartService;

    public function __construct(
        SettingsService      $settingsService,
        CartBackupService    $cartBackupService,
        CartServiceInterface $cartService,
        PayPalExpress        $paypalExpress
    ) {
        $this->settingsService = $settingsService;
        $this->cartBackupService = $cartBackupService;
        $this->paypalExpress = $paypalExpress;
        $this->cartService = $cartService;
    }

    public function getDecorated(): AbstractStartCheckoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function cancelCheckout(SalesChannelContext $context): CancelCheckoutResponse
    {
        $settings = $this->settingsService->getSettings($context->getSalesChannelId());

        if ($settings->isPaypalExpressEnabled() === false) {
            throw PaypalExpressException::paymentNotEnabled($context->getSalesChannelId());
        }


        $cart = $this->cartService->getCalculatedMainCart($context);

        if ($this->cartBackupService->isBackupExisting($context)) {
            $this->cartBackupService->restoreCart($context);
            $this->cartBackupService->clearBackup($context);
        }

        $cartExtension = $cart->getExtension(CustomFieldsInterface::MOLLIE_KEY);
        $sessionId = null;

        if ($cartExtension instanceof ArrayStruct) {
            $sessionId = $cartExtension[CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY] ?? null;
        }

        if ($sessionId === null) {
            throw PaypalExpressException::cartSessionIdIsEmpty();
        }

        try {
            $session = $this->paypalExpress->cancelSession($sessionId, $context);
        } catch (\Throwable $e) {
            //todo: remove try catch once cancel is possible from mollie
        }
        return new CancelCheckoutResponse($sessionId);
    }
}
