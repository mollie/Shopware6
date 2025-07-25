import Plugin from '../plugin';

const APPLE_PAY_INPUT_SELECTOR_OLD = '.payment-method-input.applepay';
const APPLE_PAY_INPUT_SELECTOR_NEW = '#paymentMethod';
const PAYMENT_METHOD_SELECTOR = '.payment-method';

export default class MollieApplePayPaymentMethod extends Plugin {
    static options = {
        shopUrl: null,
        hideAlways: false,
        applePayId: null,
        hideApplePayOption: false,
    };

    /**
     *
     */
    init() {
        const hideAlways = this.options.hideAlways;

        // if we don't want to always hide it,
        // then only hide it, if Apple Pay is not active
        if (!hideAlways && window.ApplePaySession && window.ApplePaySession.canMakePayments()) {
            // Apple Pay is active
            return;
        }

        // support for < Shopware 6.4
        this.hideApplePay(APPLE_PAY_INPUT_SELECTOR_OLD);

        // support for >= Shopware 6.4
        this.hideApplePay(APPLE_PAY_INPUT_SELECTOR_NEW + this.options.applePayId);

        // hide cart apple pay select option
        if (this.options.hideApplePayOption) {
            this.hideApplePaySelect(this.options.applePayId);
        }
    }

    /**
     *
     * @param innerIdentifier
     */
    hideApplePay(innerIdentifier) {
        const element = document.querySelector(innerIdentifier);
        const rootElement = this.getClosest(element, PAYMENT_METHOD_SELECTOR);

        if (!!rootElement && !!rootElement.classList) {
            rootElement.remove();
        }
    }

    /**
     *
     * @param applePayId
     */
    hideApplePaySelect(applePayId) {
        const option = document.querySelector('option[value="' + applePayId + '"]');

        if (option !== null) {
            option.remove();
        }
    }

    /**
     *
     * @returns {*}
     */
    getShopUrl() {
        // get sales channel base URL
        // so that our shop slug is correctly
        let shopSlug = this.options.shopUrl;

        if (shopSlug === undefined) {
            return '';
        }

        // remove trailing slash if existing
        // sometimes more exist
        while (shopSlug.substr(-1) === '/') {
            shopSlug = shopSlug.substr(0, shopSlug.length - 1);
        }

        return shopSlug;
    }

    /**
     *
     * @param elem
     * @param selector
     * @returns {null|*}
     */
    getClosest(elem, selector) {
        // Element.matches() polyfill
        if (!Element.prototype.matches) {
            Element.prototype.matches =
                Element.prototype.matchesSelector ||
                Element.prototype.mozMatchesSelector ||
                Element.prototype.msMatchesSelector ||
                Element.prototype.oMatchesSelector ||
                Element.prototype.webkitMatchesSelector ||
                function (s) {
                    const matches = (this.document || this.ownerDocument).querySelectorAll(s);
                    let i = matches.length;
                    // eslint-disable-next-line no-empty
                    while (--i >= 0 && matches.item(i) !== this) {}
                    return i > -1;
                };
        }

        // Get the closest matching element
        for (; elem && elem !== document; elem = elem.parentNode) {
            if (elem.matches(selector)) {
                return elem;
            }
        }

        return null;
    }
}
