<?php

namespace Kiener\MolliePayments\Components\ApplePayDirect\Services;

use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayCart;
use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayLineItem;
use Kiener\MolliePayments\Service\Router\RoutingDetector;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Contracts\Translation\TranslatorInterface;

class ApplePayFormatter
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var RoutingDetector
     */
    private $routingDetector;

    /**
     * @param TranslatorInterface $translator
     * @param RoutingDetector $routingDetector
     */
    public function __construct(TranslatorInterface $translator, RoutingDetector $routingDetector)
    {
        $this->translator = $translator;
        $this->routingDetector = $routingDetector;
    }

    /**
     * @param ShippingMethodEntity $shippingMethod
     * @param float $shippingCosts
     * @return array<mixed>
     */
    public function formatShippingMethod(ShippingMethodEntity $shippingMethod, float $shippingCosts): array
    {
        $detail = '';

        if ($shippingMethod->getDeliveryTime() !== null) {
            $detail = $shippingMethod->getDeliveryTime()->getTranslation('name') ?: $shippingMethod->getDeliveryTime()->getName();
        }

        return [
            'identifier' => $shippingMethod->getId(),
            'label' => $shippingMethod->getName(),
            'amount' => $shippingCosts,
            'detail' => $shippingMethod->getDescription() . ($detail !== '' ? ' (' . $detail . ')' : ''),
        ];
    }

    /**
     * @param ApplePayCart $cart
     * @param SalesChannelEntity $shop
     * @param bool $isTestMode
     * @return array<mixed>
     */
    public function formatCart(ApplePayCart $cart, SalesChannelEntity $shop, bool $isTestMode): array
    {
        $shopName = $shop->getName();

        # snippets in headless somehow do not work, weird..so let's do a static translation
        $isStoreApiScope = $this->routingDetector->isStoreApiRoute();

        if ($isTestMode) {
            $snippetTestMode = ($isStoreApiScope) ? 'Test Mode' : $this->translator->trans('molliePayments.testMode.label');
            $shopName .= ' (' . $snippetTestMode . ')';
        }

        # -----------------------------------------------------
        # INITIAL DATA
        # -----------------------------------------------------
        $data = [
            'label' => $shopName,
            'amount' => $this->prepareFloat($cart->getAmount()),
            'items' => [],
        ];

        # -----------------------------------------------------
        # SUBTOTAL
        # -----------------------------------------------------
        $snippetCaptionSubtotal = ($isStoreApiScope) ? 'Subtotal' : $this->translator->trans('molliePayments.payments.applePayDirect.captionSubtotal');
        $data['items'][] = [
            'label' => $snippetCaptionSubtotal,
            'type' => 'final',
            'amount' => $this->prepareFloat($cart->getProductAmount()),
        ];

        # -----------------------------------------------------
        # SHIPPING DATA
        # -----------------------------------------------------
        foreach ($cart->getShippings() as $shipping) {
            $data['items'][] = [
                'label' => $shipping->getName(),
                'type' => 'final',
                'amount' => $this->prepareFloat($shipping->getPrice()),
            ];
        }

        # -----------------------------------------------------
        # TAXES DATA
        # -----------------------------------------------------
        if ($cart->getTaxes() instanceof ApplePayLineItem) {
            $snippetCaptionTaxes = ($isStoreApiScope) ? 'Taxes' : $this->translator->trans('molliePayments.payments.applePayDirect.captionTaxes');
            $data['items'][] = [
                'label' => $snippetCaptionTaxes,
                'type' => 'final',
                'amount' => $this->prepareFloat($cart->getTaxes()->getPrice()),
            ];
        }

        # -----------------------------------------------------
        # TOTAL DATA
        # -----------------------------------------------------
        $data['total'] = [
            'label' => $shopName,
            'amount' => $this->prepareFloat($cart->getAmount()),
            'type' => 'final',
        ];

        return $data;
    }

    /**
     * Attention! When json_encode is being used it will
     * automatically display digits like this 23.9999998 instead of 23.99.
     * This is done inside json_encode! So we need to prepare
     * the value by rounding the number up to the number
     * of decimals we find here!
     *
     * @param float $value
     * @return float
     */
    private function prepareFloat(float $value)
    {
        $countDecimals = strlen((string)substr((string)strrchr((string)$value, "."), 1));

        return round($value, $countDecimals);
    }
}
