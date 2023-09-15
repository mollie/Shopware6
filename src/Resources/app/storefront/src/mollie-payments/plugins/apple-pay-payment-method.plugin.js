import Plugin from '@shopware-storefront-sdk/plugin-system/plugin.class';
import HttpClient from '../services/HttpClient';

export default class MollieApplePayPaymentMethod extends Plugin {

    /**
     *
     */
    init() {

        const me = this;

        const hideAlways = this.options.hideAlways;
        const shopUrl = this.getShopUrl();

        // if we don't want to always hide it,
        // then only hide it, if Apple Pay is not active
        if (!hideAlways && window.ApplePaySession && window.ApplePaySession.canMakePayments()) {
            // apple pay is active
            return;
        }

        // support for < Shopware 6.4
        this.hideApplePay('.payment-method-input.applepay');

        // support for >= Shopware 6.4
        // we have to find the dynamic ID and use that
        // one as a selector to hide it
        const client = new HttpClient();
        client.get(
            shopUrl + '/mollie/apple-pay/applepay-id',
            (data) => {
                me.hideApplePay('#paymentMethod' + data.id);
            }
        );


    }

    /**
     *
     * @param innerIdentifier
     */
    hideApplePay(innerIdentifier) {
        const element = document.querySelector(innerIdentifier);
        const rootElement = this.getClosest(element, '.payment-method');

        if (!!rootElement && !!rootElement.classList) {
            rootElement.remove();
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
                    while (--i >= 0 && matches.item(i) !== this) {
                    }
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