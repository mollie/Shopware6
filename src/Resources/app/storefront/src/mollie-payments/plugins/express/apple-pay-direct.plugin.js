import Plugin from '../../plugin';
import PrivacyNotesService from '../../services/express/privacy-notes.service';
import EventHandlerUtils from '../../services/event-handler.utils';
import ExpressOffcanvasService from '../../services/express/offcanvas.service';
import AddToCartService from '../../services/express/add-to-cart.service';
import ApplePaySessionFactoryService from '../../services/express/apple-pay-session-factory.service';
import ProductBoxValidator from '../../services/express/product-box-validator';
import ApplePayDirectContainer from '../../models/ApplePayDirectContainer';
import BuyBoxRepository from '../../repository/buy-box-repository';

const DISPLAY_NONE_CLS = 'd-none';

/**
 * Mollie Apple Pay Direct Plugin
 *
 * Handles Apple Pay direct payments by managing button visibility,
 * privacy validation, cart operations, and Apple Pay session creation.
 */
export default class MollieApplePayDirect extends Plugin {
    /**
     * Initialize the Apple Pay Direct plugin
     *
     * Sets up service instances, registers off-canvas events,
     * and initializes the current page.
     */
    init() {
        this._privacySection = new PrivacyNotesService(document);
        this._eventUtils = new EventHandlerUtils();
        this._cartService = new AddToCartService();
        this._productBoxValidator = new ProductBoxValidator();
        this._repoBuyBox = new BuyBoxRepository(document);

        const offCanvas = new ExpressOffcanvasService();
        offCanvas.register(this.initCurrentPage.bind(this), 'mollie-apple-pay-direct');

        this.initCurrentPage();
    }

    /**
     * Initialize Apple Pay functionality for the current page
     *
     * Finds Apple Pay buttons and containers, checks browser compatibility,
     * initializes privacy checkboxes, and sets up event listeners.
     */
    initCurrentPage() {
        this._applePayButtons = this._repoBuyBox.findApplePayButtons();
        this._applePayContainers = this._repoBuyBox.findApplePayContainers();

        if (!window.ApplePaySession || !window.ApplePaySession.canMakePayments() || this._applePayButtons.length <= 0) {
            // hide our wrapping Apple Pay containers, to avoid any wrong margins being displayed
            this.hideApplePayFeature();
            return;
        }

        // initialize GDPR checkboxes
        this._privacySection.initCheckbox();

        // show apple-pay containers
        this.showApplePayFeature();

        // init buttons
        if (this._applePayButtons) {
            for (let i = 0; i < this._applePayButtons.length; i++) {
                const button = this._applePayButtons[i];

                // assign click handler
                this._eventUtils.addEventListenerOnce(button, this.onApplePayButtonClicked.bind(this), 'click');

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

    /**
     * Show Apple Pay buttons and containers
     *
     * Removes the 'd-none' CSS class from all Apple Pay containers
     * and buttons to make them visible to the user.
     */
    showApplePayFeature() {
        if (this._applePayContainers) {
            for (let i = 0; i < this._applePayContainers.length; i++) {
                const container = this._applePayContainers[i];
                container.classList.remove(DISPLAY_NONE_CLS);
            }
        }

        if (this._applePayButtons) {
            for (let i = 0; i < this._applePayButtons.length; i++) {
                const button = this._applePayButtons[i];
                button.classList.remove(DISPLAY_NONE_CLS);
            }
        }
    }

    /**
     * Hide Apple Pay buttons and containers
     *
     * Adds the 'd-none' CSS class to all Apple Pay containers
     * and buttons to hide them from the user.
     */
    hideApplePayFeature() {
        if (this._applePayContainers) {
            for (let i = 0; i < this._applePayContainers.length; i++) {
                const container = this._applePayContainers[i];
                container.classList.add(DISPLAY_NONE_CLS);
            }
        }

        if (this._applePayButtons) {
            for (let i = 0; i < this._applePayButtons.length; i++) {
                const button = this._applePayButtons[i];
                button.classList.add(DISPLAY_NONE_CLS);
            }
        }
    }

    /**
     * Handle Apple Pay button click events
     *
     * Validates privacy settings, adds items to cart, extracts payment
     * configuration from DOM elements, and initiates Apple Pay session.
     *
     * @param {Event} event - The click event from the Apple Pay button
     */
    onApplePayButtonClicked(event) {
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
        // load the values from our buy-element container

        const divContainer = this._repoBuyBox.findClosestApplePayContainer(clickedButton);

        const container = new ApplePayDirectContainer(divContainer, clickedButton);

        // -----------------------------------------------------------------
        // add item to cart

        if (container.isProductMode()) {
            this._cartService.addItemToCart(clickedButton);
        }

        // -----------------------------------------------------------------
        // start apple pay

        const applePaySessionFactory = new ApplePaySessionFactoryService();

        const session = applePaySessionFactory.create(
            container.isProductMode(),
            container.getCountryCode(),
            container.getCurrency(),
            container.getWithPhone(),
            container.getShopSlug(),
            container.getDataProtection(),
            clickedButton,
        );

        session.begin();
    }
}
