import deepmerge from 'deepmerge';
import MollieCreditCardMandate from '../core/creditcard-mandate.plugin';

const CARD_HOLDER_SELECTOR = '#cardHolder';
const COMPONENTS_CONTAINER_SELECTOR = 'div.mollie-components-credit-card';
const CREDIT_CARD_RADIO_INPUT_SELECTOR = '#confirmPaymentForm input[type="radio"].creditcard';
const MOLLIE_CONTROLLER_SELECTOR = 'div.mollie-components-controller';
const PAYMENT_FORM_SELECTOR = '#confirmPaymentForm';
const RADIO_INPUTS_SELECTOR = '#confirmPaymentForm input[type="radio"]';
const SUBMIT_BUTTON_SELECTOR = '#confirmPaymentForm button[type="submit"]';

const DISPLAY_NONE_CLS = 'd-none';
const ERROR_CLS = 'error';
const FOCUS_CLS = 'is-focused';

export default class MollieCreditCardComponents extends MollieCreditCardMandate {
    static options = deepmerge(MollieCreditCardMandate.options, {
        customerId: null,
        locale: null,
        profileId: null,
        shopUrl: null,
        testMode: true,
    });

    init() {
        super.init();
        const me = this;
        let componentsObject = null;

        // Get an existing Mollie controller element
        const mollieController = document.querySelector(MOLLIE_CONTROLLER_SELECTOR);

        // Remove the existing Mollie controller element
        if (mollieController) {
            mollieController.remove();
        }

        // Get the elements from the DOM
        this.getElements();

        // Initialize Mollie Components instance
        if (!!this._componentsContainer && !!this._cardHolder) {
            // eslint-disable-next-line no-undef
            componentsObject = Mollie(this.options.profileId, {
                locale: this.options.locale,
                testmode: this.options.testMode,
            });
        }

        // Create components inputs
        this.createComponentsInputs(componentsObject, [
            this.getInputFields().cardHolder,
            this.getInputFields().cardNumber,
            this.getInputFields().expiryDate,
            this.getInputFields().verificationCode,
        ]);

        // Show/hide the components form based on the selected radio input
        this._radioInputs.forEach((element) => {
            element.addEventListener('change', () => {
                me.showComponents();
            });
        });

        // Submit handler
        this._submitButton.addEventListener('click', (event) => {
            event.preventDefault();
            me.submitForm(event, componentsObject, this._paymentForm);
        });

        this.registerMandateEvents();
    }

    getElements() {
        this._cardHolder = document.querySelector(CARD_HOLDER_SELECTOR);
        this._componentsContainer = document.querySelector(COMPONENTS_CONTAINER_SELECTOR);
        this._paymentForm = document.querySelector(PAYMENT_FORM_SELECTOR);
        this._radioInputs = document.querySelectorAll(RADIO_INPUTS_SELECTOR);
        this._submitButton = document.querySelector(SUBMIT_BUTTON_SELECTOR);
        this._creditCardRadioInput = document.querySelector(CREDIT_CARD_RADIO_INPUT_SELECTOR);
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

    showComponents() {
        if (this._componentsContainer) {
            if (this._creditCardRadioInput === undefined || this._creditCardRadioInput.checked === false) {
                this._componentsContainer.classList.add(DISPLAY_NONE_CLS);
            } else {
                this._componentsContainer.classList.remove(DISPLAY_NONE_CLS);
            }
        }
    }

    createComponentsInputs(componentsObject, inputs) {
        const me = this;

        inputs.forEach((element, index, arr) => {
            const component = componentsObject.createComponent(element.name, me.getDefaultProperties());
            component.mount(element.id);
            arr[index][element.name] = component;

            // Handle errors
            component.addEventListener('change', (event) => {
                const componentContainer = document.getElementById(`${element.name}`);
                const componentError = document.getElementById(`${element.errors}`);

                if (event.error && event.touched) {
                    componentContainer.classList.add(ERROR_CLS);
                    componentError.textContent = event.error;
                } else {
                    componentContainer.classList.remove(ERROR_CLS);
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

    setFocus(componentName, isFocused) {
        const element = document.querySelector(componentName);
        element.classList.toggle(FOCUS_CLS, isFocused);
    }

    disableForm() {
        if (this._submitButton) {
            this._submitButton.disabled = true;
        }
    }

    enableForm() {
        if (this._submitButton) {
            this._submitButton.disabled = false;
        }
    }

    async submitForm(event, componentsObject, paymentForm) {
        event.preventDefault();
        const me = this;
        this.disableForm();

        if (
            (this._creditCardRadioInput === undefined ||
                this._creditCardRadioInput === null ||
                this._creditCardRadioInput.checked === false) &&
            !!paymentForm
        ) {
            paymentForm.submit();
        }

        if (!!this._creditCardRadioInput && this._creditCardRadioInput.checked === true) {
            const mandateId = this.getMandateCheckedValue();
            // If the mandateId is valid, that means there is a mandate already selected,
            // so we have to call the API to save it
            // and then we continue by submitting our original payment form.
            if (this.isValidSelectedMandate(mandateId)) {
                this.client.get(
                    me.options.shopUrl +
                        '/mollie/components/store-mandate-id/' +
                        me.options.customerId +
                        '/' +
                        mandateId,
                    () => {
                        paymentForm.submit();
                    },
                    () => {
                        paymentForm.submit();
                    },
                    'application/json; charset=utf-8',
                );

                return;
            }

            // Reset possible form errors
            const verificationErrors = document.getElementById(`${this.getInputFields().verificationCode.errors}`);
            verificationErrors.textContent = '';

            // Get a payment token
            const { token, error } = await componentsObject.createToken();

            if (error) {
                this.enableForm();
                verificationErrors.textContent = error.message;
                return;
            }

            if (!error) {
                // Build query params
                const queryParams = new URLSearchParams({
                    shouldSaveCardDetail: this.shouldSaveCardDetail(),
                });

                let queryString = queryParams.toString();
                if (queryString) {
                    queryString = `?${queryString}`;
                }

                // now we finish by first calling our URL to store
                // the credit card token for the user and the current checkout
                // and then we continue by submitting our original payment form.
                this.client.get(
                    me.options.shopUrl +
                        '/mollie/components/store-card-token/' +
                        me.options.customerId +
                        '/' +
                        token +
                        queryString,
                    () => {
                        const tokenInput = document.getElementById('cardToken');
                        tokenInput.setAttribute('value', token);
                        paymentForm.submit();
                    },
                    () => {
                        paymentForm.submit();
                    },
                    'application/json; charset=utf-8',
                );
            }
        }
    }
}
