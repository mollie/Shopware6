import Plugin from '../../plugin';
import ExpressOffcanvasService from '../../services/express/offcanvas.service';
import PrivacyNotesService from '../../services/express/privacy-notes.service';
import EventHandlerUtils from '../../services/event-handler.utils';
import AddToCartService from '../../services/express/add-to-cart.service';
import ProductBoxValidator from '../../services/express/product-box-validator';
import BuyBoxRepository from '../../repository/buy-box-repository';

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

        // Bind the onPageShow method to preserve the 'this' context
        // window.addEventListener('pageshow', this.onPageShow.bind(this));

        for (let i = 0; i < expressButtons.length; i++) {
            expressButtons[i].addEventListener('click', this.onExpressCheckout.bind(this));
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

        // -----------------------------------------------------------------

        const submitUrl = clickedButton.getAttribute('data-form-action');

        const form = document.createElement('form');
        form.setAttribute('action', submitUrl);
        form.setAttribute('method', 'POST');

        document.body.insertAdjacentElement('beforeend', form);

        form.submit();
    }
}
