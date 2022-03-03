import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from '../services/HttpClient';
import DeviceDetection from 'src/helper/device-detection.helper';


export default class MollieCreditCardComponentsSw64 extends Plugin {
    static options = {
        paymentId: null,
        customerId: null,
        locale: null,
        profileId: null,
        shopUrl: null,
        testMode: true,
    };

    init() {

        try {
            this._paymentForm = DomAccess.querySelector(document, this.getSelectors().paymentForm);
            this._confirmForm = DomAccess.querySelector(document, this.getSelectors().confirmForm);
            this._confirmFormButton = DomAccess.querySelector(this._confirmForm, this.getSelectors().confirmFormButton);
        } catch (e) {
            return;
        }

        this.client = new HttpClient();

        this._cleanUpExistingElement();
        this._fixShopUrl();
        this._initializeComponentInstance();
        this._registerEvents();
    }

    _cleanUpExistingElement() {
        // Get an existing Mollie controller element
        const mollieController = document.querySelector(this.getSelectors().mollieController);

        // Remove the existing Mollie controller element
        if (mollieController) {
            mollieController.remove();
        }
    }

    _fixShopUrl() {
        // Fix the trailing slash in the shop URL
        if (this.options.shopUrl != null && this.options.shopUrl.substr(-1) === '/') {
            this.options.shopUrl = this.options.shopUrl.substr(0, this.options.shopUrl.length - 1);
        }
    }

    _initializeComponentInstance() {
        this._componentsObject = null;

        // Get the elements from the DOM
        const cardHolder = document.querySelector(this.getSelectors().cardHolder);
        const componentsContainer = document.querySelector(this.getSelectors().componentsContainer);

        // Initialize Mollie Components instance
        if (
            !!componentsContainer
            && !!cardHolder
        ) {
            // eslint-disable-next-line no-undef
            this._componentsObject = Mollie(this.options.profileId, {
                locale: this.options.locale,
                testmode: this.options.testMode,
            });
        }

        // Create components inputs
        this.createComponentsInputs();
    }

    _registerEvents() {
        this._confirmForm.addEventListener('submit', this.submitForm.bind(this));
    }

    _reactivateFormSubmit() {
        this._confirmFormButton.disabled = false;

        const loader = DomAccess.querySelector(this._confirmFormButton, '.loader', false);

        if (loader) {
            loader.remove();
        }
    }

    getSelectors() {
        return {
            cardHolder: '#cardHolder',
            componentsContainer: 'div.mollie-components-credit-card',
            creditCardRadioInput: '#changePaymentForm input[type="radio"]',
            mollieController: 'div.mollie-components-controller',
            paymentForm: '#changePaymentForm',
            confirmForm: '#confirmOrderForm',
            confirmFormButton: '#confirmOrderForm > button[type="submit"]',
        };
    }

    getDefaultProperties() {
        return {
            styles: {
                base: {
                    backgroundColor: '#fff',
                    fontSize: '14px',
                    padding: '10px 10px',
                    '::placeholder': {
                        color: 'rgba(68, 68, 68, 0.2)',
                    },
                },
                valid: {
                    color: '#090',
                },
                invalid: {
                    backgroundColor: '#fff1f3',
                },
            },
        };
    }

    getInputFields() {
        return {
            cardHolder: {
                name: 'cardHolder',
                id: '#cardHolder',
                errors: 'cardHolderError',
            },
            cardNumber: {
                name: 'cardNumber',
                id: '#cardNumber',
                errors: 'cardNumberError',
            },
            expiryDate: {
                name: 'expiryDate',
                id: '#expiryDate',
                errors: 'expiryDateError',
            },
            verificationCode: {
                name: 'verificationCode',
                id: '#verificationCode',
                errors: 'verificationCodeError',
            },
        };
    }

    createComponentsInputs() {
        const me = this;
        const inputs = [
            this.getInputFields().cardHolder,
            this.getInputFields().cardNumber,
            this.getInputFields().expiryDate,
            this.getInputFields().verificationCode,
        ];

        if (this._componentsObject !== null) {

            inputs.forEach((element, index, arr) => {

                const component = this._componentsObject.createComponent(element.name, me.getDefaultProperties());
                component.mount(element.id);
                arr[index][element.name] = component;

                // Handle errors
                component.addEventListener('change', event => {
                    const componentContainer = document.getElementById(`${element.name}`);
                    const componentError = document.getElementById(`${element.errors}`);

                    if (event.error && event.touched) {
                        componentContainer.classList.add('error');
                        componentError.textContent = event.error;
                    } else {
                        componentContainer.classList.remove('error');
                        componentError.textContent = '';
                    }
                });

                // Handle labels
                component.addEventListener('focus', () => {
                    me.setFocus(`${element.id}`, true);
                });
                component.addEventListener('blur', () => {
                    me.setFocus(`${element.id}`, false);
                });
            });
        }
    }

    setFocus(componentName, isFocused) {
        const element = document.querySelector(componentName);
        element.classList.toggle('is-focused', isFocused);
    }

    /**
     *
     * @param event
     * @returns {Promise<void>}
     */
    async submitForm(event) {

        const me = this;
        const paymentForm = this._confirmForm;


        const creditCardRadioInput = document.querySelector(`${this.getSelectors().creditCardRadioInput}[value="${this.options.paymentId}"]`);

        // check if we have any credit card forms or elements visible
        // if not, we just continue with standard
        if ((creditCardRadioInput === undefined || creditCardRadioInput === null || creditCardRadioInput.checked === false) && !!this._confirmForm) {
            return;
        }

        // check if we have existing forms, but if we do not have
        // activated the credit card payment method
        // then also continue with standard
        if (!!creditCardRadioInput && creditCardRadioInput.checked === false) {
            return;
        }

        // MOLLIE CREDIT CARD IS USED
        // ---------------------------------------------------------------------------------------------

        // as soon as we know that it's just "us"
        // then we prevent the default behaviour and
        // inject our own flow
        event.preventDefault();


        // Reset possible form errors
        const verificationErrors = document.getElementById(`${this.getInputFields().verificationCode.errors}`);
        verificationErrors.textContent = '';

        // Get a payment token
        const {token, error} = await this._componentsObject.createToken();

        if (error) {
            verificationErrors.textContent = error.message;
            this._reactivateFormSubmit();
            verificationErrors.scrollIntoView();
            return;
        }

        // now we finish by first calling our URL to store
        // the credit card token for the user and the current checkout
        // and then we continue by submitting our original payment form.
        this.client.get(
            me.options.shopUrl + '/mollie/components/store-card-token/' + me.options.customerId + '/' + token,
            function () {
                me.continueShopwareCheckout(paymentForm);
            },
            function () {
                me.continueShopwareCheckout(paymentForm);
            },
            'application/json; charset=utf-8'
        );
    }


    /**
     * In IE we have to add the TOS checkbox to the form
     * when starting it from Javascript.
     * the original TOS is based on form-associations which do not work in IE.
     * So we just grab the value and pass it as hidden form so that
     * Shopware will receive it.
     *
     * @param form
     */
    continueShopwareCheckout(form) {

        if (DeviceDetection.isIEBrowser()) {
            const createInput = function (name, val) {
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.name = name;
                input.checked = val;
                input.style.display = 'none';

                return input;
            };

            const checkTOS = document.getElementById('tos');

            // we might not always have the TOS checkbox (editOrder)
            // but if we have it, we have to add it again
            if (checkTOS !== undefined && checkTOS !== null) {
                form.insertAdjacentElement('beforeend', createInput('tos', checkTOS.checked));
            }
        }

        form.submit();
    }

}
