import Plugin from '../plugin';
import ApplePaySessionFactory from '../services/apple-pay-session-factory';
import ExpressButtonsRepository from '../repository/express-buttons-repository';
import BuyElementRepository from '../repository/buy-element-repository';
import { MOLLIE_BIND_EXPRESS_EVENTS } from './mollie-express-actions.plugin';

const APPLE_PAY_BUTTON_SELECTOR = '.js-apple-pay';
const APPLE_PAY_CONTAINER_SELECTOR = '.js-apple-pay-container';
const APPLE_PAY_BUTTON_PROCESSED_CLS = 'processed';

const COUNTRY_CODE_INPUT_SELECTOR = 'input[name="countryCode"]';
const CURRENCY_INPUT_SELECTOR = 'input[name="currency"]';
const MODE_INPUT_SELECTOR = 'input[name="mode"]';
const WITH_PHONE_INPUT_SELECTOR = 'input[name="withPhone"]';
const DATA_PROTECTION_INPUT_SELECTOR = 'input[name="acceptedDataProtection"]';
const SHOP_URL_ATTR = 'data-shop-url';

const DISPLAY_NONE_CLS = 'd-none';
const DEFAULT_MODE = 'productMode';

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
        const expressButtons = expressButtonsRepository.findAll(APPLE_PAY_BUTTON_SELECTOR);
        const applePayContainers = document.querySelectorAll(APPLE_PAY_CONTAINER_SELECTOR);

        if (expressButtons.length === 0 && applePayContainers.length === 0) {
            return;
        }

        document.dispatchEvent(new CustomEvent(MOLLIE_BIND_EXPRESS_EVENTS, { detail: expressButtons }));

        applePayContainers.forEach((container) => {
            container.classList.remove(DISPLAY_NONE_CLS);
        });

        expressButtons.forEach((button) => {
            button.classList.remove(DISPLAY_NONE_CLS);
            button.addEventListener('click', this.onExpressCheckout);
        });
    }

    onExpressCheckout(event) {
        const clickedButton = event.target;

        if (!clickedButton.classList.contains(APPLE_PAY_BUTTON_PROCESSED_CLS)) {
            return;
        }

        const buyElementRepository = new BuyElementRepository();
        const buyElement = buyElementRepository.find(clickedButton);

        const countryCode = buyElement.querySelector(COUNTRY_CODE_INPUT_SELECTOR).value;
        const currency = buyElement.querySelector(CURRENCY_INPUT_SELECTOR).value;
        const mode = buyElement.querySelector(MODE_INPUT_SELECTOR).value;
        const withPhone = parseInt(buyElement.querySelector(WITH_PHONE_INPUT_SELECTOR).value);
        const dataProtection = buyElement.querySelector(DATA_PROTECTION_INPUT_SELECTOR);
        const isProductMode = mode === DEFAULT_MODE;

        let shopSlug = clickedButton.getAttribute(SHOP_URL_ATTR);

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
