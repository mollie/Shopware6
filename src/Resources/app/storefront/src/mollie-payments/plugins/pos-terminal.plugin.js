import HttpClient from '../services/http-client';
import Plugin from '../plugin';

export default class MolliePosTerminalPlugin extends Plugin {
    _shopUrl = '';
    _customerId = '';

    _dropdownTerminals = null;

    _isModalForm = false;

    _container = null;
    _paymentForm = null;
    _radioInputs = null;
    _posRadioInput = null;

    /**
     *
     */
    init() {
        this._container = document.querySelector('div.mollie-pos-terminals');

        if (this._container === undefined || this._container === null) {
            return;
        }

        this.initControls();

        // now check if we even have a payment form
        // if not, then we are not on the checkout page
        // but maybe in the accounts page instead...we dont need components there
        if (this._paymentForm === null) {
            return;
        }

        // if we don't have the issuers dropdown available, then we can't even do anything
        if (this._dropdownTerminals === null) {
            return;
        }

        this.registerEvents();

        // update the visibility of our issuer dropdown list
        this.updateDropdownVisibility(this._posRadioInput, this._container, this._dropdownTerminals);

        this.updateTerminal(
            this._shopUrl,
            this._customerId,
            this._posRadioInput,
            this._dropdownTerminals,
            function () {},
        );
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
        this._dropdownTerminals = document.querySelector('#posTerminals');

        this._paymentForm = document.querySelector('#changePaymentForm');

        if (this._paymentForm === undefined || this._paymentForm === null) {
            return;
        }

        this._radioInputs = this._paymentForm.querySelectorAll('input[type="radio"]');
        this._posRadioInput = this._paymentForm.querySelector('input[type="radio"].pointofsale');
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
        const allRadioInputs = this._radioInputs;
        const posRadioInput = this._posRadioInput;
        const terminalsDropdown = this._dropdownTerminals;

        // add event to toggle the dropdown visibility
        // when switching payment methods
        allRadioInputs.forEach((element) => {
            element.addEventListener('change', () => {
                this.updateDropdownVisibility(posRadioInput, container, terminalsDropdown);
            });
        });

        terminalsDropdown.addEventListener('change', async () => {
            this.updateTerminal(shopUrl, customerId, posRadioInput, terminalsDropdown, function () {});
        });
    }

    /**
     *
     * @param posRadio
     * @param container
     * @param dropdown
     */
    updateDropdownVisibility(posRadio, container, dropdown) {
        let terminalRequired = false;

        if (posRadio === undefined || posRadio.checked === false) {
            container.classList.add('d-none');
        } else {
            container.classList.remove('d-none');
            terminalRequired = true;
        }

        if (dropdown !== undefined) {
            dropdown.required = terminalRequired;
        }
    }

    /**
     *
     * @param shopUrl
     * @param customerId
     * @param posRadio
     * @param terminalsDropdown
     * @param onCompleted
     */
    updateTerminal(shopUrl, customerId, posRadio, terminalsDropdown, onCompleted) {
        if (posRadio === undefined) {
            onCompleted('POS Radio Input not defined');
            return;
        }

        if (posRadio === null) {
            onCompleted('POS Radio Input not found');
            return;
        }

        if (posRadio.checked === false) {
            onCompleted('POS payment not active');
            return;
        }

        if (terminalsDropdown === undefined) {
            onCompleted('POS terminals not defined');
            return;
        }

        if (terminalsDropdown === null) {
            onCompleted('POS terminals not found');
            return;
        }

        if (terminalsDropdown.value === '') {
            onCompleted('no POS terminal selected');
            return;
        }

        const client = new HttpClient();

        client.get(
            shopUrl + '/mollie/pos/store-terminal/' + customerId + '/' + terminalsDropdown.value,
            function () {
                onCompleted('terminal updated successfully');
            },
            function () {
                onCompleted('error when updating terminal');
            },
            'application/json; charset=utf-8',
        );
    }
}
