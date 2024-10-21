import Plugin from '@shopware-storefront-sdk/plugin-system/plugin.class';
import {MOLLIE_EXPRESS_CHECKOUT_EVENT} from './mollie-express-actions.plugin';
import ExpressButtonsRepository from '../repository/ExpressButtonsRepository';
import {PrivacyNoteElement} from '../repository/PrivacyNoteElement';

export default class PayPalExpressPlugin extends Plugin {

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
        const expressButtonsRepository = new ExpressButtonsRepository();

        const expressButtons = expressButtonsRepository.findAll('.mollie-paypal-button');

        if (expressButtons.length === 0) {
            return;
        }

        expressButtons.forEach((button) => {
            button.addEventListener(MOLLIE_EXPRESS_CHECKOUT_EVENT, this.onExpressCheckout)
        });

    }

    onExpressCheckout(event) {
        const clickedButton = event.target;

        const submitUrl = clickedButton.getAttribute('data-form-action');

        const form = document.createElement('form');
        form.setAttribute('action', submitUrl);
        form.setAttribute('method', 'POST');

        const privacyNoteElement = new PrivacyNoteElement();
        const privacyNote = privacyNoteElement.find(clickedButton);
        if (privacyNote instanceof HTMLDivElement) {
            const checkbox = privacyNoteElement.getCheckbox(privacyNote);
            const checkboxValue = checkbox.checked ? 'on' : '';
            form.setAttribute('acceptedDataProtection', checkboxValue);
        }

        document.body.insertAdjacentElement('beforeend', form);

        form.submit();
    }


}