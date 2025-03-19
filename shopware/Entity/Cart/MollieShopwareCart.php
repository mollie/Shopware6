<?php
declare(strict_types=1);

namespace Mollie\Shopware\Entity\Cart;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;

class MollieShopwareCart
{
    private Cart $cart;

    private ?Struct $cartExtension;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
        $this->cartExtension = $cart->getExtension(CustomFieldsInterface::MOLLIE_KEY);
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setSingleProductExpressCheckout(bool $true): void
    {
        $this->setExtensionKey(CustomFieldsInterface::SINGLE_PRODUCT_EXPRESS_CHECKOUT, $true);
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
        $this->cartExtension[CustomFieldsInterface::ACCEPTED_DATA_PROTECTION] = $accepted;
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

    private function getExtensionKey(string $key, $defaultValue)
    {
        if (! $this->cartExtension instanceof Struct) {
            return $defaultValue;
        }

        if (! array_key_exists($key, $this->cartExtension->getVars())) {
            return $defaultValue;
        }

        return $this->cartExtension[$key];
    }

    private function setExtensionKey(string $key, string $value): void
    {
        $this->prepareExtension();

        $this->cartExtension[$key] = $value;
    }

    private function clearExtensionKey(string $key): void
    {
        if (! $this->cartExtension instanceof Struct) {
            return;
        }

        if (! array_key_exists($key, $this->cartExtension->getVars())) {
            return;
        }

        unset($this->cartExtension[$key]);
    }

    private function prepareExtension(): void
    {
        if ($this->cartExtension instanceof Struct) {
            return;
        }

        $this->cartExtension = new ArrayStruct([]);

        $this->cart->addExtension(CustomFieldsInterface::MOLLIE_KEY, $this->cartExtension);
    }
}
