import Plugin from '../Plugin';
import {PrivacyNoteElement} from '../repository/PrivacyNoteElement';
import BuyButtonRepository from '../repository/BuyButtonRepository';
import ExpressButtonsRepository from '../repository/ExpressButtonsRepository';
import ExpressAddToCart from '../services/ExpressAddToCart';

export const MOLLIE_EXPRESS_CHECKOUT_EVENT = 'MollieStartExpressCheckout';

export class MollieExpressActions extends Plugin {


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
        const expressButtons = expressButtonsRepository.findAll();

        if (expressButtons.length === 0) {
            return;
        }
        const privacyNote = new PrivacyNoteElement();
        privacyNote.observeButtons();

        const buyButtonRepository = new BuyButtonRepository();

        expressButtons.forEach((button) => {


            button.classList.remove('d-none');
            button.addEventListener('click', this.onButtonClick)

            const buyButton = buyButtonRepository.find(button);
            if (!(buyButton instanceof HTMLButtonElement)) {
                return;
            }

            if (buyButton.hasAttribute('disabled')) {
                button.classList.add('d-none');
                button.removeEventListener('click', this.onButtonClick)
            }

            const buyButtonForm = buyButton.closest('form');
            if (!(buyButtonForm instanceof HTMLFormElement)) {
                return;
            }

            buyButtonForm.addEventListener('change', () => {

                button.classList.remove('d-none');
                button.addEventListener('click', this.onButtonClick)

                if (buyButton.hasAttribute('disabled')) {

                    button.classList.add('d-none');
                    button.removeEventListener('click', this.onButtonClick)
                }

            })

        });

    }

    async onButtonClick(event) {
        let target = event.target;
        if (!(target instanceof HTMLButtonElement)) {
            target = target.closest('button');
        }

        const privacyNote = new PrivacyNoteElement();

        const privacyNoteElement = privacyNote.find(target);

        if (privacyNoteElement instanceof HTMLDivElement) {
            privacyNoteElement.classList.add('was-validated');
            const isValid = privacyNote.validate(privacyNoteElement);
            if (isValid === false) {
                return;
            }
        }
        const mollieEvent = new Event(MOLLIE_EXPRESS_CHECKOUT_EVENT);

        const expressAddToCart = new ExpressAddToCart();
        await expressAddToCart.addItemToCart(target).then(() => {
            target.dispatchEvent(mollieEvent);
        });
    }
}