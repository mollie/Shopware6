import deepmerge from 'deepmerge';
import MollieCreditCardMandate from '../core/creditcard-mandate.plugin';
import DeviceDetection from '../services/DeviceDetection';
import CsrfAjaxMode from '../services/CsrfAjaxMode';
import ConfirmPageRepository from '../services/ConfirmPageRepository';

export default class MollieCreditCardComponentsSw64 extends MollieCreditCardMandate {


    static options = deepmerge(MollieCreditCardMandate.options, {
        paymentId: null,
        customerId: null,
        locale: null,
        profileId: null,
        shopUrl: null,
        testMode: true,
    });


    /**
     *
     */
    init() {

        super.init();

        try {

            const repoConfirmPage = new ConfirmPageRepository(document);

            this._paymentForm = repoConfirmPage.getPaymentForm();
            this._confirmForm = repoConfirmPage.getConfirmForm();
            this._confirmFormButton = repoConfirmPage.getSubmitButton();

        } catch (e) {
            console.error('Mollie Credit Card components: Required HTML elements not found on this page!');
            return;
        }

        this._initializeComponentInstance();
        this._registerEvents();
        this.registerMandateEvents();
    }

    /**
     *
     * @private
     */
    _initializeComponentInstance() {
        // Get the elements from the DOM
        const cardHolder = document.querySelector(this.getSelectors().cardHolder);
        const componentsContainer = document.querySelector(this.getSelectors().componentsContainer);

        // Initialize Mollie Components instance
        if (!!componentsContainer && !!cardHolder && !window.mollieComponentsObject) {

            // eslint-disable-next-line no-undef
            window.mollieComponentsObject = Mollie(
                this.options.profileId,
                {
                    locale: this.options.locale,
                    testmode: this.options.testMode,
                }
            );

            window.mollieComponents = {};
        }

        // Create components inputs
        this.createComponentsInputs();
    }

    _registerEvents() {
        if (this._confirmForm !== null) {
            this._confirmForm.addEventListener('submit', this.submitForm.bind(this));
        }
    }

    _reactivateFormSubmit() {
        this._confirmFormButton.disabled = false;

        // TODO check this
        const loader = this._confirmFormButton.querySelector('.loader');

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

        if (window.mollieComponentsObject) {
            inputs.forEach((element, index, arr) => {
                const component = this._mountMollieComponent(element.id, element.name);
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

    _mountMollieComponent(componentId, componentName) {
        if (!window.mollieComponents[componentName]) {
            window.mollieComponents[componentName] = window.mollieComponentsObject.createComponent(
                componentName,
                this.getDefaultProperties()
            );
        } else {
            window.mollieComponents[componentName].unmount();
        }

        window.mollieComponents[componentName].mount(componentId);

        return window.mollieComponents[componentName];
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

        const mandateId = this.getMandateCheckedValue();
        // If the mandateId is valid, that means there is a mandate already selected,
        // so we have to call the API to save it
        // and then we continue by submitting our original payment form.
        if (this.isValidSelectedMandate(mandateId)) {
            this.client.get(
                me.options.shopUrl + '/mollie/components/store-mandate-id/' + me.options.customerId + '/' + mandateId,
                () => {
                    me.continueShopwareCheckout(paymentForm);
                },
                () => {
                    me.continueShopwareCheckout(paymentForm);
                },
                'application/json; charset=utf-8'
            );

            return;
        }


        // Reset possible form errors
        const verificationErrors = document.getElementById(`${this.getInputFields().verificationCode.errors}`);
        verificationErrors.textContent = '';

        // Get a payment token
        const {token, error} = await window.mollieComponentsObject.createToken();

        if (error) {
            verificationErrors.textContent = error.message;
            this._reactivateFormSubmit();
            verificationErrors.scrollIntoView();
            return;
        }

        // Build query params
        const queryParams = new URLSearchParams({
            'shouldSaveCardDetail': this.shouldSaveCardDetail(),
        });

        let queryString = queryParams.toString();
        if (queryString) {
            queryString = `?${queryString}`;
        }

        // now we finish by first calling our URL to store
        // the credit card token for the user and the current checkout
        // and then we continue by submitting our original payment form.
        this.client.get(
            me.options.shopUrl + '/mollie/components/store-card-token/' + me.options.customerId + '/' + token + queryString,
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
        const csrfMode = new CsrfAjaxMode(window.csrf);
        if (!csrfMode.isActive()) {
            form.submit();
        }
    }

}
