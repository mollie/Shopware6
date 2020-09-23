import Plugin from 'src/plugin-system/plugin.class';

export default class MollieCreditCardComponents extends Plugin {
    static options = {
        customerId: null,
        locale: null,
        profileId: null,
        shopUrl: null,
        testMode: true,
    };

    init() {
        let me = this;
        let componentsObject = null;

        // Get an existing Mollie controller element
        const mollieController = document.querySelector(this.getSelectors().mollieController);

        // Remove the existing Mollie controller element
        if (!!mollieController) {
            mollieController.remove();
        }

        // Fix the trailing slash in the shop URL
        if (this.options.shopUrl.substr(-1) === '/') {
            this.options.shopUrl = this.options.shopUrl.substr(0, this.options.shopUrl.length - 1);
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
                this.getInputFields().verificationCode
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
                        color: 'rgba(68, 68, 68, 0.2)'
                    }
                },
                valid: {
                    color: '#090'
                },
                invalid: {
                    backgroundColor: '#fff1f3'
                }
            }
        };
    }

    getInputFields() {
        return {
            cardHolder: {
                name: 'cardHolder',
                id: '#cardHolder',
                errors: 'cardHolderError'
            },
            cardNumber: {
                name: 'cardNumber',
                id: '#cardNumber',
                errors: 'cardNumberError'
            },
            expiryDate: {
                name: 'expiryDate',
                id: '#expiryDate',
                errors: 'expiryDateError'
            },
            verificationCode: {
                name: 'verificationCode',
                id: '#verificationCode',
                errors: 'verificationCodeError'
            }
        };
    }

    showComponents() {
        const creditCardRadioInput = document.querySelector(this.getSelectors().creditCardRadioInput);
        const componentsContainer = document.querySelector(this.getSelectors().componentsContainer);

        if (!!componentsContainer) {
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
        let me = this;

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

        if (!!submitButton) {
            submitButton.disabled = true;
        }
    }

    enableForm() {
        const submitButton = document.querySelector(this.getSelectors().submitButton);

        if (!!submitButton) {
            submitButton.disabled = false;
        }
    }

    async submitForm(event, componentsObject, paymentForm) {
        event.preventDefault();
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
                const fetchUrl = this.options.shopUrl + '/mollie/components/store-card-token/' + this.options.customerId + '/' + token;

                // Store the token on the customer
                if (
                    !!fetchUrl
                    && !!paymentForm
                ) {
                    fetch(fetchUrl, {headers: {'Content-Type': 'application/json; charset=utf-8'}})
                        .then(() => {
                            // Add token to the form
                            const tokenInput = document.getElementById('cardToken');
                            tokenInput.setAttribute('value', token);
                            paymentForm.submit();
                        })
                        .catch(paymentForm.submit());
                }
            }
        }
    }
}