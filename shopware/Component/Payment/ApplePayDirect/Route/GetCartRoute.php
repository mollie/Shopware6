<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayAmount;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayCart;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayLineItem;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayShippingLineItem;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class GetCartRoute extends AbstractGetCartRoute
{
    public function __construct(
        private CartService $cartService,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: Translator::class)]
        private AbstractTranslator $translator,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractGetCartRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.apple-pay.cart', path: '/store-api/mollie/applepay/cart', methods: ['GET'])]
    public function cart(Request $request, SalesChannelContext $salesChannelContext): GetCartResponse
    {
        $localeCode = '';
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $languageInfo = $salesChannelContext->getLanguageInfo();
        /** @phpstan-ignore-next-line  */
        if ($languageInfo instanceof LanguageInfo) {
            $localeCode = $languageInfo->localeCode;
        }
        $logData = [
            'localeCode' => $localeCode,
            'salesChannelId' => $salesChannelId,
        ];
        $this->logger->info('Start - Apple Pay cart route request', $logData);
        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $applePayCart = $this->getApplePayCart($cart, $salesChannelContext, $localeCode);

        $this->logger->info('Finished - Apple Pay cart route request', $logData);

        return new GetCartResponse($applePayCart, $cart);
    }

    private function getApplePayCart(Cart $cart, SalesChannelContext $salesChannelContext, string $localeCode): ApplePayCart
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $apiSettings = $this->settingsService->getApiSettings($salesChannelId);
        $shopName = (string) $salesChannelContext->getSalesChannel()->getName();
        $isTestMode = $apiSettings->isTestMode();

        $this->translator->injectSettings($salesChannelId, $salesChannelContext->getLanguageId(), $localeCode, $salesChannelContext->getContext());

        $testModeLabel = $this->translator->trans('molliePayments.testMode.label');
        if (mb_strlen($testModeLabel) === 0) {
            $testModeLabel = 'Test mode';
        }
        $subTotalLabel = $this->translator->trans('molliePayments.payments.applePayDirect.captionSubtotal');
        if (mb_strlen($subTotalLabel) === 0) {
            $subTotalLabel = 'Subtotal';
        }
        $taxLabel = $this->translator->trans('molliePayments.payments.applePayDirect.captionTaxes');
        if (mb_strlen($taxLabel) === 0) {
            $taxLabel = 'Taxes';
        }

        if ($isTestMode) {
            $shopName = sprintf('%s (%s)', $shopName, $testModeLabel);
        }
        $price = $cart->getPrice();

        $applePayCart = new ApplePayCart($shopName, new ApplePayAmount($price->getTotalPrice()));

        $taxAmount = 0.0;
        $subTotal = 0.0;

        foreach ($cart->getLineItems() as $lineItem) {
            $totalPrice = 0.0;

            $price = $lineItem->getPrice();
            if ($price instanceof CalculatedPrice) {
                $totalPrice = $price->getTotalPrice();
                /** @var CalculatedTax $tax */
                foreach ($price->getCalculatedTaxes() as $tax) {
                    $taxAmount += $tax->getTax();
                }
            }
            $subTotal += $totalPrice;
            $item = new ApplePayLineItem((string) $lineItem->getLabel(), new ApplePayAmount($totalPrice));
            $applePayCart->addItem($item);
        }

        $taxItem = new ApplePayLineItem($subTotalLabel, new ApplePayAmount($subTotal));
        $applePayCart->addItem($taxItem);

        /** @var Delivery $delivery */
        foreach ($cart->getDeliveries() as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            $deliveryTaxes = $shippingCosts->getCalculatedTaxes();

            /** @var CalculatedTax $tax */
            foreach ($deliveryTaxes as $tax) {
                $taxAmount += $tax->getTax();
            }

            $item = new ApplePayShippingLineItem((string) $delivery->getShippingMethod()->getName(), new ApplePayAmount($shippingCosts->getTotalPrice()));
            $applePayCart->addItem($item);
        }

        if ($taxAmount > 0) {
            $taxItem = new ApplePayLineItem($taxLabel, new ApplePayAmount($taxAmount));
            $applePayCart->addItem($taxItem);
        }

        return $applePayCart;
    }
}
