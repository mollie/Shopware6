import Plugin from '../Plugin';
import ApplePaySessionFactory from '../services/ApplePaySessionFactory';
import ExpressButtonsRepository from '../repository/ExpressButtonsRepository';
import BuyElementRepository from '../repository/BuyElementRepository';
import { MOLLIE_BIND_EXPRESS_EVENTS } from './mollie-express-actions.plugin';

export default class MollieApplePayDirect extends Plugin {
    /**
     *
     */
    init() {
        const pluginOffCanvasInstances = window.PluginManager.getPluginList().OffCanvasCart.get('instances');
        if (pluginOffCanvasInstances.length > 0) {
            pluginOffCanvasInstances.forEach((pluginOffCanvas) => {
                pluginOffCanvas.$emitter.subscribe('offCanvasOpened', this.bindEvents.bind(this));
            });
        }

        this.bindEvents();
    }

    bindEvents() {
        if (!window.ApplePaySession || !window.ApplePaySession.canMakePayments()) {
            return;
        }
        const expressButtonsRepository = new ExpressButtonsRepository();
        const expressButtons = expressButtonsRepository.findAll('.js-apple-pay');
        const applePayContainers = document.querySelectorAll('.js-apple-pay-container');

        if (expressButtons.length === 0 && applePayContainers.length === 0) {
            return;
        }

        document.dispatchEvent(new CustomEvent(MOLLIE_BIND_EXPRESS_EVENTS, { detail: expressButtons }));

        applePayContainers.forEach((container) => {
            container.classList.remove('d-none');
        });

        expressButtons.forEach((button) => {
            button.classList.remove('d-none');
            button.addEventListener('click', this.onExpressCheckout);
        });
    }

    onExpressCheckout(event) {
        const clickedButton = event.target;

        if (!clickedButton.classList.contains('processed')) {
            return;
        }

        const buyElementRepository = new BuyElementRepository();
        const buyElement = buyElementRepository.find(clickedButton);

        const countryCode = buyElement.querySelector('input[name="countryCode"]').value;
        const currency = buyElement.querySelector('input[name="currency"]').value;
        const mode = buyElement.querySelector('input[name="mode"]').value;
        const withPhone = parseInt(buyElement.querySelector('input[name="withPhone"]').value);
        const dataProtection = buyElement.querySelector('input[name="acceptedDataProtection"]');
        const isProductMode = mode === 'productMode';

        let shopSlug = clickedButton.getAttribute('data-shop-url');

        if (shopSlug.slice(-1) === '/') {
            shopSlug = shopSlug.slice(0, -1);
        }

        const applePaySessionFactory = new ApplePaySessionFactory();
        const session = applePaySessionFactory.create(
            isProductMode,
            countryCode,
            currency,
            withPhone,
            shopSlug,
            dataProtection,
            clickedButton,
        );
        session.begin();
    }
}
