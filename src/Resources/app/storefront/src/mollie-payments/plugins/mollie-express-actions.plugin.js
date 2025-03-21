import Plugin from '../plugin';
import { PrivacyNoteElementRepository } from '../repository/privacy-note-element-repository';
import BuyButtonRepository from '../repository/buy-button-repository';
import ExpressAddToCart from '../services/express-add-to-cart';

export const MOLLIE_BIND_EXPRESS_EVENTS = 'BindExpressEvents';

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
        const privacyNote = new PrivacyNoteElementRepository();
        privacyNote.observeButtons();

        document.addEventListener(MOLLIE_BIND_EXPRESS_EVENTS, (event) => {
            const expressButtons = event.detail;

            if (expressButtons.length === 0) {
                return;
            }

            const buyButtonRepository = new BuyButtonRepository();

            expressButtons.forEach((button) => {
                button.classList.remove('d-none');
                button.addEventListener('click', this.onButtonClick);

                const buyButton = buyButtonRepository.find(button);
                if (!(buyButton instanceof HTMLButtonElement)) {
                    return;
                }

                if (buyButton.hasAttribute('disabled')) {
                    button.classList.add('d-none');
                    button.removeEventListener('click', this.onButtonClick);
                }

                const buyButtonForm = buyButton.closest('form');
                if (!(buyButtonForm instanceof HTMLFormElement)) {
                    return;
                }

                buyButtonForm.addEventListener('change', () => {
                    button.classList.remove('d-none');
                    button.addEventListener('click', this.onButtonClick);

                    if (buyButton.hasAttribute('disabled')) {
                        button.classList.add('d-none');
                        button.removeEventListener('click', this.onButtonClick);
                    }
                });
            });
        });
    }

    onButtonClick(event) {
        let target = event.target;
        if (!(target instanceof HTMLButtonElement)) {
            target = target.closest('button');
        }

        if (target.classList.contains('processed')) {
            return;
        }

        const privacyNote = new PrivacyNoteElementRepository();

        const privacyNoteElement = privacyNote.find(target);

        if (privacyNoteElement instanceof HTMLDivElement) {
            privacyNoteElement.classList.add('was-validated');
            const isValid = privacyNote.validate(privacyNoteElement);
            if (isValid === false) {
                return;
            }
        }

        const expressAddToCart = new ExpressAddToCart();

        expressAddToCart.addItemToCartOrSkip(target).then(() => {
            // set to processed
            target.classList.add('processed');
            // now trigger click event again for the real button
            const mollieEvent = new event.constructor(event.type, event);
            target.dispatchEvent(mollieEvent);
        });
    }
}
