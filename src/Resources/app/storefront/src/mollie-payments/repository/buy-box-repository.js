export default class BuyBoxRepository {
    /**
     *
     * @param document
     */
    constructor(document) {
        this._document = document;
    }

    /**
     * Finds the closes Shopware buy button for the provided
     * Mollie Express button. This is especially useful on a listing page
     * where we have multiple products and multiple buy buttons.
     * @param expressButton
     * @returns {*|null}
     */
    findClosestShopwareBuyButton(expressButton) {
        const buyBox = this.findClosestBuyBox(expressButton);

        if (buyBox === null) {
            return null;
        }

        return buyBox.querySelector('.btn-buy');
    }

    findClosestApplePayContainer(target) {
        return target.closest('.mollie-apple-pay-direct');
    }

    findClosestBuyBox(target) {
        let buyElementContainer = target.closest('.product-action');

        if (buyElementContainer === null) {
            buyElementContainer = target.closest('.product-detail-form-container');
        }
        if (buyElementContainer === null) {
            buyElementContainer = target.closest('.offcanvas-cart-actions');
        }

        if (buyElementContainer === null) {
            buyElementContainer = target.closest('.checkout-aside-container');
        }
        if (buyElementContainer === null) {
            buyElementContainer = target.closest('.checkout-main');
        }

        return buyElementContainer;
    }

    findAllExpressButtons(target, additionalSelector = null) {
        let selector = '.mollie-express-button';
        if (additionalSelector !== null) {
            selector += additionalSelector;
        }
        return target.querySelectorAll(selector);
    }

    findApplePayButtons() {
        return this._document.querySelectorAll('.mollie-express-button.js-apple-pay');
    }

    findApplePayContainers() {
        return this._document.querySelectorAll('.js-apple-pay-container');
    }

    findPayPalExpressButtons() {
        return this._document.querySelectorAll('.mollie-paypal-button');
    }

    findClosestPrivacyBox(expressButton) {
        const buyElementContainer = this.findClosestBuyBox(expressButton);
        if (buyElementContainer === null) {
            return null;
        }
        return buyElementContainer.querySelector('.mollie-privacy-note');
    }

    getPrivacyBoxCheckbox(privacyNote) {
        return privacyNote.querySelector('input[name="acceptedDataProtection"]');
    }
}
