<?php
declare(strict_types=1);

namespace Mollie\Shopware\Entity\Cart;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Struct\ArrayStruct;

class MollieShopwareCart
{
    private Cart $cart;

    private ArrayStruct $cartExtension;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
        $extension = $cart->getExtension(CustomFieldsInterface::MOLLIE_KEY) ?? new ArrayStruct();
        $this->cartExtension = new ArrayStruct($extension->getVars());
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setSingleProductExpressCheckout(bool $true): void
    {
        $this->setExtensionKey(CustomFieldsInterface::SINGLE_PRODUCT_EXPRESS_CHECKOUT, (string) $true);
    }

    public function isSingleProductExpressCheckout(): bool
    {
        return (bool) $this->getExtensionKey(CustomFieldsInterface::SINGLE_PRODUCT_EXPRESS_CHECKOUT, false);
    }

    public function isDataProtectionAccepted(): int
    {
        return (int) $this->getExtensionKey(CustomFieldsInterface::ACCEPTED_DATA_PROTECTION, 0);
    }

    public function setDataProtectionAccepted(int $accepted): void
    {
        $this->setExtensionKey(CustomFieldsInterface::ACCEPTED_DATA_PROTECTION, (string) $accepted);
    }

    // <editor-fold desc="paypal-express">

    public function getPayPalExpressSessionID(): string
    {
        return (string) $this->getExtensionKey(CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY, '');
    }

    public function setPayPalExpressSessionID(string $sessionId): void
    {
        $this->setExtensionKey(CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY, $sessionId);
    }

    public function getPayPalExpressAuthId(): string
    {
        return (string) $this->getExtensionKey(CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID, '');
    }

    public function setPayPalExpressAuthenticateId(string $authenticatedId): void
    {
        $this->setExtensionKey(CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID, $authenticatedId);
    }

    /**
     * Gets if the user is in PPE mode and has successfully authenticated
     */
    public function isPayPalExpressComplete(): bool
    {
        if (mb_strlen($this->getPayPalExpressSessionID()) <= 0) {
            return false;
        }

        if (mb_strlen($this->getPayPalExpressAuthId()) <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Gets if the user has started PPE, but is somehow has not successfully finished the authentication
     */
    public function isPayPalExpressIncomplete(): bool
    {
        return $this->getPayPalExpressSessionID() !== '' && $this->getPayPalExpressAuthId() === '';
    }

    public function clearPayPalExpress(): void
    {
        $this->clearExtensionKey(CustomFieldsInterface::SINGLE_PRODUCT_EXPRESS_CHECKOUT);
        $this->clearExtensionKey(CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY);
        $this->clearExtensionKey(CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID);
    }

    // </editor-fold>

    /**
     * @param mixed $defaultValue
     *
     * @return mixed
     */
    private function getExtensionKey(string $key, $defaultValue)
    {
        if (! array_key_exists($key, $this->cartExtension->getVars())) {
            return $defaultValue;
        }

        return $this->cartExtension[$key];
    }

    private function setExtensionKey(string $key,string $value): void
    {
        $this->prepareExtension();

        $this->cartExtension[$key] = $value;
    }

    private function clearExtensionKey(string $key): void
    {
        if (! array_key_exists($key, $this->cartExtension->getVars())) {
            return;
        }

        unset($this->cartExtension[$key]);
    }

    private function prepareExtension(): void
    {
        $this->cart->addExtension(CustomFieldsInterface::MOLLIE_KEY, $this->cartExtension);
    }
}
