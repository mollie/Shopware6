const SCRIPT_URL = 'https://js.mollie.com/v2/mollie.js';

/**
 * Loads mollie.js v2 and hands out Checkout objects.
 *
 * The script cannot be part of the offcanvas markup: the offcanvas content is injected as
 * HTML, and script tags inserted that way are never executed. It is therefore loaded here,
 * once per page, and every mount waits for the same promise.
 */
export default class MollieCheckoutService {
    load() {
        if (!window.mollieExpressScriptPromise) {
            window.mollieExpressScriptPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = SCRIPT_URL;
                script.onload = () => resolve();
                script.onerror = () => reject(new Error('Mollie: could not load ' + SCRIPT_URL));
                document.head.appendChild(script);
            });
        }

        return window.mollieExpressScriptPromise;
    }

    /**
     * v2 registers itself as window.Mollie2 when v1 (the credit card components) already
     * occupies window.Mollie, so both globals have to be considered.
     */
    resolve() {
        if (window.Mollie2 && typeof window.Mollie2.Checkout === 'function') {
            return window.Mollie2;
        }

        if (window.Mollie && typeof window.Mollie.Checkout === 'function') {
            return window.Mollie;
        }

        return null;
    }

    createCheckout(clientAccessToken, locale) {
        const mollie = this.resolve();

        if (mollie === null) {
            throw new Error('Mollie: mollie.js v2 is not available');
        }

        return mollie.Checkout(clientAccessToken, { locale: locale });
    }
}
