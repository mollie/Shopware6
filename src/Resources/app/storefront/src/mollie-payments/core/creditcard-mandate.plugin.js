import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from '../services/HttpClient';

/**
 * This plugin manage the credit card mandate of the customer
 */
export default class MollieCreditCardMandate extends Plugin {
    static options = {
        newCardMandateOption: null,
        mollieCreditCardFormClass: '.mollie-components-credit-card',
        mollieCreditCardMandateInput: 'input[name="mollieCreditCardMandate"]',
        mollieShouldSaveCardDetailInput: 'input[name="mollieShouldSaveCardDetail"]',
    };

    init() {
        this.client = new HttpClient();
        this._fixShopUrl()
        this.registerMandateEvents();
    }

    /**
     * Register mandate events
     * Call this function to listen to all events relative to the mandate feature
     */
    registerMandateEvents() {
        const {
            newCardMandateOption,
            mollieCreditCardFormClass,
            mollieCreditCardMandateInput,
        } = this.options;

        if (!newCardMandateOption) {
            return;
        }

        this.mollieCreditCarfFormEl = DomAccess.querySelector(document, mollieCreditCardFormClass, false);
        this.mollieCreditCardMandateEls = DomAccess.querySelectorAll(document, mollieCreditCardMandateInput, false);

        if (!this.mollieCreditCarfFormEl || !this.mollieCreditCardMandateEls) {
            return
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

        this.mollieCreditCardMandateEls.forEach(el => {
            el.addEventListener('change', () => {
                this.onMandateInputChange(this.getMandateCheckedValue());
            });
        });
    }

    /**
     * Get value of `mollieCreditCardMandate` checked radio input
     */
    getMandateCheckedValue() {
        const { mollieCreditCardMandateInput } = this.options;

        const mandateInput = DomAccess.querySelector(document, `${ mollieCreditCardMandateInput }:checked`, false);
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
        const { mollieShouldSaveCardDetailInput } = this.options;

        const shouldSaveCardDetail = DomAccess.querySelector(document, mollieShouldSaveCardDetailInput, false);
        if (!shouldSaveCardDetail) {
            return false;
        }

        return shouldSaveCardDetail.checked;
    }

    onMandateInputChange(mandateValue) {
        const { newCardMandateOption } = this.options
        if (mandateValue === newCardMandateOption) {
            this.mollieCreditCarfFormEl.classList.remove('d-none');
            return;
        }

        this.mollieCreditCarfFormEl.classList.add('d-none');
    }
}
