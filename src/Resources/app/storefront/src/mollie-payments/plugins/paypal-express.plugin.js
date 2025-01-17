import Plugin from '@shopware-storefront-sdk/plugin-system/plugin.class';
import ExpressButtonsRepository from '../repository/ExpressButtonsRepository';
import {PrivacyNoteElement} from '../repository/PrivacyNoteElement';
import {MOLLIE_BIND_EXPRESS_EVENTS} from './mollie-express-actions.plugin';

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

        document.dispatchEvent(new CustomEvent(MOLLIE_BIND_EXPRESS_EVENTS, {detail: expressButtons}));

        expressButtons.forEach((button) => {
            button.addEventListener('click', this.onExpressCheckout)
        });

    }

    onExpressCheckout(event) {

        const clickedButton = event.target;
        if (!clickedButton.classList.contains('processed')) {
            return;
        }


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