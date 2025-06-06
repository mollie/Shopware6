import Plugin from '../../plugin';
import ExpressButtonsRepository from '../../repository/express-buttons-repository';
import BuyElementRepository from '../../repository/buy-element-repository';
import PrivacyNotesService from '../../services/express/privacy-notes.service';
import EventHandlerUtils from '../../services/event-handler.utils';
import ExpressOffcanvasService from '../../services/express/offcanvas.service';
import AddToCartService from '../../services/express/add-to-cart.service';
import ApplePaySessionFactoryService from "../../services/express/apple-pay-session-factory.service";
import BuyButtonRepository from "../../repository/buy-button-repository";

const DISPLAY_NONE_CLS = 'd-none';
const PROCESSED_CLS = 'processed';
const WAS_VALIDATED_CLS = 'was-validated';
const DISABLED_ATTR = 'disabled';


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

        this._offCanvas = new ExpressOffcanvasService();
        this._repoExpressButtons = new ExpressButtonsRepository();
        this._privacySection = new PrivacyNotesService();
        this._eventUtils = new EventHandlerUtils();
        this._buyElementRepository = new BuyElementRepository();
        this._cartService = new AddToCartService();
        this._repoBuyButtons = new BuyButtonRepository();

        this._offCanvas.register(this.initCurrentPage.bind(this), 'mollie-apple-pay-direct');

        this.initCurrentPage();
    }

    /**
     * Initialize Apple Pay functionality for the current page
     *
     * Finds Apple Pay buttons and containers, checks browser compatibility,
     * initializes privacy checkboxes, and sets up event listeners.
     */
    initCurrentPage() {

        this._applePayButtons = this._repoExpressButtons.findApplePayButtons();
        this._applePayContainers = this._repoExpressButtons.findApplePayContainers();

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

                if (this.isPaymentAllowed(button)) {
                    this._eventUtils.addEventListenerOnce(button, this.onApplePayButtonClicked.bind(this), 'click');
                } else {
                    button.classList.add(DISPLAY_NONE_CLS);
                }
            }
        }
    }

    isPaymentAllowed(applePayButton) {

        // check our closest shopware buy button
        // and verify if buying is allowed for our product
        const closestShopwareBuyButton = this._repoBuyButtons.find(applePayButton);

        if (!closestShopwareBuyButton instanceof HTMLButtonElement) {
            console.log('1');
            return false;
        }

        if (closestShopwareBuyButton.hasAttribute(DISABLED_ATTR)) {
            console.log('2');
            return false;
        }

        const closestShopwareBuyForm = closestShopwareBuyButton.closest('form');

        if (!closestShopwareBuyForm instanceof HTMLFormElement) {
            console.log('3');
            return false;
        }

        console.log('4');

        return true;
        //  closestShopwareBuyForm.addEventListener('change', () => {

        //      applePayButton.classList.remove(DISPLAY_NONE_CLS);

        //      if (closestShopwareBuyButton.hasAttribute(DISABLED_ATTR)) {
        //          applePayButton.classList.add(DISPLAY_NONE_CLS);
        //          //    button.removeEventListener('click', this.onApplePayButtonClicked);
        //      }
        //  });
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

        const clickedButton = event.target;

        if (!this._privacySection.validateForButton(clickedButton)) {
            return;
        }

        // -----------------------------------------------------------------

        this._cartService.addItemToCartOrSkip(clickedButton);

        // -----------------------------------------------------------------
        // load the values from our buy-element container

        const buyElement = this._buyElementRepository.find(clickedButton);

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

        // -----------------------------------------------------------------
        // start apple pay

        const applePaySessionFactory = new ApplePaySessionFactoryService();

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
