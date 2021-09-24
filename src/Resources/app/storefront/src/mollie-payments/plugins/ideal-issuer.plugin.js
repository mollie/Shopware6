import Plugin from 'src/plugin-system/plugin.class';

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
        this.registerEvents();

        // update the visibility of our
        // issuer dropdown list
        this.updateIssuerVisibility(this._iDealRadioInput, this._container)

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

        this._radioInputs = this._paymentForm.querySelectorAll('input[type="radio"]');
        this._iDealRadioInput = this._paymentForm.querySelector('input[type="radio"].ideal');
    }

    /**
     *
     */
    registerEvents() {

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
                this.updateIssuerVisibility(iDealRadioInput, container)
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
     */
    updateIssuerVisibility(iDealRadio, container) {
        if (iDealRadio === undefined || iDealRadio.checked === false) {
            container.classList.add('d-none');
        } else {
            container.classList.remove('d-none');
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

        const fetchUrl = shopUrl + '/mollie/ideal/store-issuer/' + customerId + '/' + issuersDropdown.value;

        fetch(
            fetchUrl,
            {
                headers: {'Content-Type': 'application/json; charset=utf-8'},
            })
            .then(() => {
                onCompleted('issuer updated successfully');
            })
            .catch(() => {
                onCompleted('error when updating issuer');
            });
    }

}