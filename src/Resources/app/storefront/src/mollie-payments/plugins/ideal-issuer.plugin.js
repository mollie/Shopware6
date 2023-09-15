import Plugin from '@shopware-storefront-sdk/plugin-system/plugin.class';
import HttpClient from '../services/HttpClient'


export default class MollieIDealIssuer extends Plugin {

    _shopUrl = '';
    _customerId = '';

    _isModalForm = false;

    _container = null;
    _paymentForm = null;
    _issuersDropdown = null;
    _radioInputs = null;
    _iDealRadioInput = null;


    /**
     *
     */
    init() {

        this._container = document.querySelector('div.mollie-ideal-issuer');

        if (this._container === undefined || this._container === null) {
            return;
        }


        // load our controls
        // and register the necessary events
        this.initControls();

        // now check if we even have a payment form
        // if not, then we are not on the checkout page
        // but maybe in the accounts page instead...we dont need components there
        if (this._paymentForm === null) {
            return;
        }

        // if we dont have the issuers dropdown available, then we can't even do anything
        if (this._issuersDropdown === null) {
            return;
        }

        this.registerEvents();

        // update the visibility of our
        // issuer dropdown list
        this.updateIssuerVisibility(this._iDealRadioInput, this._container, this._issuersDropdown)

        // if we do not have the old modal form, but
        // the new inline form, then automatically set the
        // currently selected issuer as the selected on of the customer.
        // this is for consistency.
        if (!this._isModalForm) {
            this.updateIssuer(this._shopUrl, this._customerId, this._iDealRadioInput, this._issuersDropdown, function () {
            });
        }
    }

    /**
     *
     */
    initControls() {
        this._shopUrl = this._container.getAttribute('data-shop-url');

        if (this._shopUrl.substr(-1) === '/') {
            this._shopUrl = this._shopUrl.substr(0, this._shopUrl.length - 1);
        }

        this._customerId = this._container.getAttribute('data-customer-id');
        this._issuersDropdown = document.querySelector('#iDealIssuer');

        // Shopware < 6.4
        const oldPaymentForm = document.querySelector('#confirmPaymentForm');
        // Shopware >= 6.4
        const newPaymentForm = document.querySelector('#changePaymentForm');

        if (newPaymentForm) {
            this._paymentForm = newPaymentForm;
        } else {
            this._isModalForm = true;
            this._paymentForm = oldPaymentForm;
        }

        if (this._paymentForm === undefined || this._paymentForm === null) {
            return;
        }

        this._radioInputs = this._paymentForm.querySelectorAll('input[type="radio"]');
        this._iDealRadioInput = this._paymentForm.querySelector('input[type="radio"].ideal');
    }

    /**
     *
     */
    registerEvents() {

        if (this._paymentForm === null) {
            return;
        }

        // create locally scoped variables
        // for async functions. this is required
        const shopUrl = this._shopUrl;
        const customerId = this._customerId;
        const container = this._container;
        const paymentForm = this._paymentForm;
        const allRadioInputs = this._radioInputs;
        const iDealRadioInput = this._iDealRadioInput;
        const issuersDropdown = this._issuersDropdown;


        // add event to toggle the dropdown visibility
        // when switching payment methods
        allRadioInputs.forEach((element) => {
            element.addEventListener('change', () => {
                this.updateIssuerVisibility(iDealRadioInput, container, issuersDropdown)
            });
        });


        // if we have the old modal form, then we have a
        // dedicated "submit" button that we use to trigger
        // the change of our selected issuer for our user.
        // if we have the new form directly on the confirm, then we do this immediately
        // while the user switches the values in the dropdown
        if (!this._isModalForm) {

            issuersDropdown.addEventListener('change', async () => {
                this.updateIssuer(shopUrl, customerId, iDealRadioInput, issuersDropdown, function () {
                });
            });

        } else {

            const submitButton = paymentForm.querySelector('button[type="submit"]');

            submitButton.addEventListener('click', async () => {
                this.updateIssuer(shopUrl, customerId, iDealRadioInput, issuersDropdown, function () {
                });
            });
        }
    }

    /**
     *
     * @param iDealRadio
     * @param container
     * @param dropdown
     */
    updateIssuerVisibility(iDealRadio, container, dropdown) {

        let issuerRequired = false;

        if (iDealRadio === undefined || iDealRadio.checked === false) {
            container.classList.add('d-none');
        } else {
            container.classList.remove('d-none');
            issuerRequired = true;
        }

        if (dropdown !== undefined) {
            dropdown.required = issuerRequired;
        }
    }

    /**
     *
     * @param shopUrl
     * @param customerId
     * @param iDealRadio
     * @param issuersDropdown
     * @param onCompleted
     */
    updateIssuer(shopUrl, customerId, iDealRadio, issuersDropdown, onCompleted) {

        if (iDealRadio === undefined) {
            onCompleted('iDEAL Radio Input not defined');
            return;
        }

        if (iDealRadio === null) {
            onCompleted('iDEAL Radio Input not found');
            return;
        }

        if (iDealRadio.checked === false) {
            onCompleted('iDEAL payment not active');
            return;
        }

        if (issuersDropdown === undefined) {
            onCompleted('iDEAL issuers not defined');
            return;
        }

        if (issuersDropdown === null) {
            onCompleted('iDEAL issuers not found');
            return;
        }

        const client = new HttpClient();

        client.get(
            shopUrl + '/mollie/ideal/store-issuer/' + customerId + '/' + issuersDropdown.value,
            function () {
                onCompleted('issuer updated successfully');
            },
            function () {
                onCompleted('error when updating issuer');
            },
            'application/json; charset=utf-8'
        );
    }

}