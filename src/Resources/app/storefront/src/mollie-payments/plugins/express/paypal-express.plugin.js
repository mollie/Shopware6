import Plugin from '../../plugin';
import ExpressOffcanvasService from '../../services/express/offcanvas.service';
import PrivacyNotesService from '../../services/express/privacy-notes.service';
import EventHandlerUtils from '../../services/event-handler.utils';
import AddToCartService from '../../services/express/add-to-cart.service';
import ProductBoxValidator from '../../services/express/product-box-validator';
import BuyBoxRepository from '../../repository/buy-box-repository';

const DISPLAY_NONE_CLS = 'd-none';

export default class PayPalExpressPlugin extends Plugin {
    init() {
        this._privacySection = new PrivacyNotesService(document);
        this._eventUtils = new EventHandlerUtils();
        this._cartService = new AddToCartService();
        this._productBoxValidator = new ProductBoxValidator();
        this._repoBuyBox = new BuyBoxRepository(document);

        const offCanvas = new ExpressOffcanvasService();
        offCanvas.register(this.bindEvents.bind(this), 'mollie-paypal-express');

        this.bindEvents();
    }

    bindEvents() {
        const expressButtons = this._repoBuyBox.findPayPalExpressButtons();

        if (expressButtons.length === 0) {
            return;
        }

        // initialize GDPR checkboxes
        this._privacySection.initCheckbox();

        for (let i = 0; i < expressButtons.length; i++) {
            const button = expressButtons[i];

            // assign click handler
            this._eventUtils.addEventListenerOnce(button, this.onExpressCheckout.bind(this), 'click');

            // update button visibility based on product box validation
            this.updateButtonVisibility(button);

            // if we have a custom product plugin, then we need to listen for changes
            // on the product buy box, because in that case the button is enabled/disabled and
            // then we have to update our express button visibility
            const buyForm = this._repoBuyBox.findClosestBuyBox(button);
            buyForm.addEventListener('change', () => {
                this.updateButtonVisibility(button);
            });
        }
    }

    updateButtonVisibility(button) {
        if (this._productBoxValidator.isCheckoutPossible(button)) {
            button.hidden = false;
            button.classList.remove(DISPLAY_NONE_CLS);
        } else {
            button.hidden = true;
            button.classList.add(DISPLAY_NONE_CLS);
        }
    }

    onExpressCheckout(event) {
        const clickedButton = event.currentTarget;

        // -----------------------------------------------------------------
        // validate if checkout is possible

        if (!this._productBoxValidator.isCheckoutPossible(clickedButton)) {
            return;
        }

        if (!this._privacySection.validateForExpressButton(clickedButton)) {
            return;
        }

        this._cartService.addItemToCart(clickedButton);

        // -----------------------------------------------------------------

        const submitUrl = clickedButton.getAttribute('data-form-action');

        const form = document.createElement('form');
        form.setAttribute('action', submitUrl);
        form.setAttribute('method', 'POST');

        document.body.insertAdjacentElement('beforeend', form);

        form.submit();
    }
}
