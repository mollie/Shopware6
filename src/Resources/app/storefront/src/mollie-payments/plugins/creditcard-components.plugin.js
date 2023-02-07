import deepmerge from 'deepmerge';
import MollieCreditCardMandate from '../core/creditcard-mandate.plugin';

export default class MollieCreditCardComponents extends MollieCreditCardMandate {
    static options =  deepmerge(MollieCreditCardMandate.options, {
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
        const mollieController = document.querySelector(this.getSelectors().mollieController);

        // Remove the existing Mollie controller element
        if (mollieController) {
            mollieController.remove();
        }

        // Get the elements from the DOM
        const cardHolder = document.querySelector(this.getSelectors().cardHolder);
        const componentsContainer = document.querySelector(this.getSelectors().componentsContainer);
        const paymentForm = document.querySelector(this.getSelectors().paymentForm);
        const radioInputs = document.querySelectorAll(this.getSelectors().radioInputs);
        const submitButton = document.querySelector(this.getSelectors().submitButton);

        // Initialize Mollie Components instance
        if (
            !!componentsContainer
            && !!cardHolder
        ) {
            // eslint-disable-next-line no-undef
            componentsObject = Mollie(this.options.profileId, {
                locale: this.options.locale,
                testmode: this.options.testMode,
            });
        }

        // Create components inputs
        this.createComponentsInputs(
            componentsObject,
            [
                this.getInputFields().cardHolder,
                this.getInputFields().cardNumber,
                this.getInputFields().expiryDate,
                this.getInputFields().verificationCode,
            ]
        );

        // Show/hide the components form based on the selected radio input
        radioInputs.forEach((element) => {
            element.addEventListener('change', () => {
                me.showComponents();
            });
        });

        // Submit handler
        submitButton.addEventListener('click', (event) => {
            event.preventDefault();
            me.submitForm(event, componentsObject, paymentForm);
        });

        this.registerMandateEvents();
    }

    getSelectors() {
        return {
            cardHolder: '#cardHolder',
            componentsContainer: 'div.mollie-components-credit-card',
            creditCardRadioInput: '#confirmPaymentForm input[type="radio"].creditcard',
            mollieController: 'div.mollie-components-controller',
            paymentForm: '#confirmPaymentForm',
            radioInputs: '#confirmPaymentForm input[type="radio"]',
            submitButton: '#confirmPaymentForm button[type="submit"]',
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

    showComponents() {
        const creditCardRadioInput = document.querySelector(this.getSelectors().creditCardRadioInput);
        const componentsContainer = document.querySelector(this.getSelectors().componentsContainer);

        if (componentsContainer) {
            if (
                creditCardRadioInput === undefined
                || creditCardRadioInput.checked === false
            ) {
                componentsContainer.classList.add('d-none');
            } else {
                componentsContainer.classList.remove('d-none');
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

    setFocus(componentName, isFocused) {
        const element = document.querySelector(componentName);
        element.classList.toggle('is-focused', isFocused);
    }

    disableForm() {
        const submitButton = document.querySelector(this.getSelectors().submitButton);

        if (submitButton) {
            submitButton.disabled = true;
        }
    }

    enableForm() {
        const submitButton = document.querySelector(this.getSelectors().submitButton);

        if (submitButton) {
            submitButton.disabled = false;
        }
    }

    async submitForm(event, componentsObject, paymentForm) {
        event.preventDefault();
        const me = this;
        this.disableForm();

        const creditCardRadioInput = document.querySelector(this.getSelectors().creditCardRadioInput);

        if (
            (
                creditCardRadioInput === undefined
                || creditCardRadioInput === null
                || creditCardRadioInput.checked === false
            )
            && !!paymentForm
        ) {
            paymentForm.submit();
        }

        if (
            !!creditCardRadioInput
            && creditCardRadioInput.checked === true
        ) {
            const mandateId = this.getMandateCheckedValue();
            // If the mandateId is valid, that means there is a mandate already selected,
            // so we have to call the API to save it
            // and then we continue by submitting our original payment form.
            if (this.isValidSelectedMandate(mandateId)) {
                this.client.get(
                    me.options.shopUrl + '/mollie/components/store-mandate-id/' + me.options.customerId + '/' + mandateId,
                    () => {
                        paymentForm.submit();
                    },
                    () => {
                        paymentForm.submit();
                    },
                    'application/json; charset=utf-8'
                );

                return;
            }

            // Reset possible form errors
            const verificationErrors = document.getElementById(`${this.getInputFields().verificationCode.errors}`);
            verificationErrors.textContent = '';

            // Get a payment token
            const {token, error} = await componentsObject.createToken();

            if (error) {
                this.enableForm();
                verificationErrors.textContent = error.message;
                return;
            }

            if (!error) {
                // Build query params
                const queryParams = new URLSearchParams({
                    'shouldSaveCardDetail': this.shouldSaveCardDetail(),
                });

                let queryString = queryParams.toString();
                if (queryString){
                    queryString = `?${queryString}`;
                }

                // now we finish by first calling our URL to store
                // the credit card token for the user and the current checkout
                // and then we continue by submitting our original payment form.
                this.client.get(
                    me.options.shopUrl + '/mollie/components/store-card-token/' + me.options.customerId + '/' + token + queryString,
                    () => {
                        const tokenInput = document.getElementById('cardToken');
                        tokenInput.setAttribute('value', token);
                        paymentForm.submit();
                    },
                    () => {
                        paymentForm.submit();
                    },
                    'application/json; charset=utf-8'
                );
            }
        }
    }
}
