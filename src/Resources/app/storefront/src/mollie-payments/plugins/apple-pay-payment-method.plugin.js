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
                function(s) {
                    let matches = (this.document || this.ownerDocument).querySelectorAll(s), i = matches.length;
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

    init() {
        const element = document.querySelector('.payment-method-input.applepay');
        const rootElement = this.getClosest(element, '.payment-method');

        if (
            !!rootElement
            && !!rootElement.classList
        ) {
            // eslint-disable-next-line no-undef
            if (!window.ApplePaySession || !window.ApplePaySession.canMakePayments()) {
                rootElement.classList.add('d-none');
            }
        }
    }
}