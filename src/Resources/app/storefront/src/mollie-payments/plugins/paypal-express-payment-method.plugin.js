import Plugin from '@shopware-storefront-sdk/plugin-system/plugin.class';
import HttpClient from '../services/HttpClient';

export default class PaypalExpressPaymentMethodPlugin extends Plugin {

    /**
     *
     */
    init() {

        const me = this;

        const hideAlways = this.options.hideAlways;
        const isUsed = this.options.isUsed;
        const shopUrl = this.getShopUrl();


        console.log(isUsed);

        // support for >= Shopware 6.4
        // we have to find the dynamic ID and use that
        // one as a selector to hide it
        const client = new HttpClient();

        if (isUsed) {
            client.get(
                shopUrl + '/mollie/paypal-express/paypal-id',
                (data) => {
                    me.hidePaypalExpress('#paymentMethod' + data.id);
                }
            );
        } else {
            client.get(
                shopUrl + '/mollie/paypal-express/paypal-express-id',
                (data) => {
                    me.hidePaypalExpress('#paymentMethod' + data.id);
                }
            );
        }
    }

    /**
     *
     * @param innerIdentifier
     */
    hidePaypalExpress(innerIdentifier) {
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