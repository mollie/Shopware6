import Plugin from '../plugin';
import ExpressButtonsRepository from '../repository/express-buttons-repository';
import { PrivacyNoteElementRepository } from '../repository/privacy-note-element-repository';
import { MOLLIE_BIND_EXPRESS_EVENTS } from './mollie-express-actions.plugin';

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

        document.dispatchEvent(new CustomEvent(MOLLIE_BIND_EXPRESS_EVENTS, { detail: expressButtons }));

        window.addEventListener('pageshow', this.onPageShow);

        expressButtons.forEach((button) => {
            button.addEventListener('click', this.onExpressCheckout);
        });
    }

    onPageShow() {
        const expressButtonsRepository = new ExpressButtonsRepository();

        const expressButtons = expressButtonsRepository.findAll('.mollie-paypal-button');

        if (expressButtons.length === 0) {
            return;
        }

        expressButtons.forEach((button) => {
            if (!button.classList.contains('processed')) {
                return;
            }
            // remove processed again, so that it doesn't look disabled
            // because a BACK button in the browser would not refresh the page
            // and therefore it would still look disabled (even though it would work)
            button.classList.remove('processed');
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

        const privacyNoteElement = new PrivacyNoteElementRepository();
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
