import Plugin from '../../plugin';
import ExpressOffcanvasService from '../../services/express/offcanvas.service';
import MollieCheckoutService from '../../services/express/mollie-checkout.service';

const CONTAINER_SELECTOR = '[data-mollie-express-component]';
const MOUNTED_ATTRIBUTE = 'data-mollie-express-component-mounted';

export default class MollieExpressComponentsPlugin extends Plugin {
    init() {
        this._checkoutService = new MollieCheckoutService();

        // the offcanvas cart is loaded via ajax, so its container does not exist yet when the
        // page is initialized and has to be mounted again on every offcanvas render
        const offCanvas = new ExpressOffcanvasService();
        offCanvas.register(this.mountAll.bind(this), 'mollie-express-components');

        this.mountAll();
    }

    mountAll() {
        const containers = document.querySelectorAll(CONTAINER_SELECTOR);

        if (containers.length === 0) {
            return;
        }

        this._checkoutService
            .load()
            .then(() => {
                containers.forEach((container) => this.mount(container));
            })
            .catch((error) => {
                console.error(error);
            });
    }

    mount(container) {
        if (container.hasAttribute(MOUNTED_ATTRIBUTE)) {
            return;
        }

        const clientAccessToken = container.getAttribute('data-client-access-token');

        if (!clientAccessToken) {
            return;
        }

        try {
            const checkout = this._checkoutService.createCheckout(
                clientAccessToken,
                container.getAttribute('data-locale'),
            );

            checkout.create('express-component', this.getOptions(container)).mount(container);
            container.setAttribute(MOUNTED_ATTRIBUTE, 'true');
        } catch (error) {
            console.error(error);
        }
    }

    /**
     * Button visibility comes from the plugin configuration: a payment method the plugin
     * already renders its own express button for is hidden inside the component.
     */
    getOptions(container) {
        const options = container.getAttribute('data-options');

        if (!options) {
            return {};
        }

        try {
            return JSON.parse(options);
        } catch (error) {
            console.error(error);
            return {};
        }
    }
}
