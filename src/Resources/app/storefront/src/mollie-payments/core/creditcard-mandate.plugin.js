import HttpClient from '../services/http-client';
import Plugin from '../plugin';

/**
 * This plugin manage the credit card mandate of the customer
 */

const DISPLAY_NONE_CLS = 'd-none';

export default class MollieCreditCardMandate extends Plugin {
    static options = {
        newCardMandateOption: null,
        mollieCreditCardFormClass: '.mollie-components-credit-card',
        mollieCreditCardMandateInput: 'input[name="mollieCreditCardMandate"]',
        mollieShouldSaveCardDetailInput: 'input[name="mollieShouldSaveCardDetail"]',
    };

    init() {
        this.client = new HttpClient();
        this._fixShopUrl();
    }

    /**
     * Register mandate events
     * Call this function to listen to all events relative to the mandate feature
     */
    registerMandateEvents() {
        const { newCardMandateOption } = this.options;

        if (!newCardMandateOption) {
            return;
        }

        this.mollieCreditCarfFormEl = document.querySelector(this.options.mollieCreditCardFormClass);
        this.mollieCreditCardMandateEls = document.querySelectorAll(this.options.mollieCreditCardMandateInput);

        if (!this.mollieCreditCarfFormEl || !this.mollieCreditCardMandateEls) {
            return;
        }

        this._registerRadioButtonsEvent();
    }

    _fixShopUrl() {
        // Fix the trailing slash in the shop URL
        if (this.options.shopUrl != null && this.options.shopUrl.substr(-1) === '/') {
            this.options.shopUrl = this.options.shopUrl.substr(0, this.options.shopUrl.length - 1);
        }
    }

    /**
     * Register mandate radio inputs event
     */
    _registerRadioButtonsEvent() {
        // Init the mandate change before listen its event
        this.onMandateInputChange(this.getMandateCheckedValue());

        this.mollieCreditCardMandateEls.forEach((el) => {
            el.addEventListener('change', () => {
                this.onMandateInputChange(this.getMandateCheckedValue());
            });
        });
    }

    /**
     * Get value of `mollieCreditCardMandate` checked radio input
     */
    getMandateCheckedValue() {
        const mandateInput = document.querySelector(`${this.options.mollieCreditCardMandateInput}:checked`);
        if (!mandateInput || !mandateInput.value) {
            return null;
        }

        return mandateInput.value;
    }

    isValidSelectedMandate(mandateId) {
        return mandateId && mandateId !== this.options.newCardMandateOption;
    }

    /**
     * Get value of `mollieShouldSaveCardDetail` checkbox input
     */
    shouldSaveCardDetail() {
        const shouldSaveCardDetail = document.querySelector(this.options.mollieShouldSaveCardDetailInput);
        if (!shouldSaveCardDetail) {
            return false;
        }

        return shouldSaveCardDetail.checked;
    }

    onMandateInputChange(mandateValue) {
        if (mandateValue === this.options.newCardMandateOption) {
            this.mollieCreditCarfFormEl.classList.remove(DISPLAY_NONE_CLS);
        } else {
            this.mollieCreditCarfFormEl.classList.add(DISPLAY_NONE_CLS);
        }
    }
}
