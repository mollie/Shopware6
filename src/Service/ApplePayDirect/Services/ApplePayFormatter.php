<?php

namespace Kiener\MolliePayments\Service\ApplePayDirect\Services;


use Kiener\MolliePayments\Service\ApplePayDirect\Models\ApplePayCart;
use Kiener\MolliePayments\Service\ApplePayDirect\Models\ApplePayLineItem;
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
     * @param TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param ShippingMethodEntity $shippingMethod
     * @param $shippingCosts
     * @return array
     */
    public function formatShippingMethod(ShippingMethodEntity $shippingMethod, $shippingCosts)
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
     * @return array
     */
    public function formatCart(ApplePayCart $cart, SalesChannelEntity $shop, $isTestMode)
    {
        $shopName = $shop->getName();

        if ($isTestMode) {
            $shopName .= ' (' . $this->translator->trans('molliePayments.testMode.label') . ')';
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
        $data['items'][] = [
            'label' => $this->translator->trans('molliePayments.payments.applePayDirect.captionSubtotal'),
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
            $data['items'][] = [
                'label' => $this->translator->trans('molliePayments.payments.applePayDirect.captionTaxes'),
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
     * @param $value
     * @return float
     */
    private function prepareFloat($value)
    {
        $countDecimals = strlen(substr(strrchr($value, "."), 1));

        return round($value, $countDecimals);
    }

}
