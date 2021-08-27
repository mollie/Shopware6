import Plugin from 'src/plugin-system/plugin.class';

export default class MollieApplePayPaymentMethod extends Plugin {


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

    /**
     *
     */
    init() {

        if (window.ApplePaySession && window.ApplePaySession.canMakePayments()) {
            // apple pay is active
            return;
        }

        let me = this;


        // support for < Shopware 6.4
        this.hideApplePay('.payment-method-input.applepay');

        // support for >= Shopware 6.4
        // we have to find the dynamic ID and use that
        // one as a selector to hide it
        fetch('/mollie/apple-pay/applepay-id')
            .then(response => response.json())
            .then(function (data) {
                me.hideApplePay('#paymentMethod' + data.id);
            });
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

}