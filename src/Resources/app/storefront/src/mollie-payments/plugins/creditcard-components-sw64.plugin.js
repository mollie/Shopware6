import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

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
            confirmFormButton: '#confirmFormSubmit',
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

    async submitForm(event) {
        event.preventDefault();

        const creditCardRadioInput = document.querySelector(`${this.getSelectors().creditCardRadioInput}[value="${this.options.paymentId}"]`);

        if (
            (
                creditCardRadioInput === undefined
                || creditCardRadioInput === null
                || creditCardRadioInput.checked === false
            )
            && !!this._confirmForm
        ) {
            this._confirmForm.submit();
        }

        if (
            !!creditCardRadioInput
            && creditCardRadioInput.checked === true
        ) {
            // Reset possible form errors
            const verificationErrors = document.getElementById(`${this.getInputFields().verificationCode.errors}`);
            verificationErrors.textContent = '';

            // Get a payment token
            const {token, error} = await this._componentsObject.createToken();

            if (error) {
                verificationErrors.textContent = error.message;
                this._reactivateFormSubmit();
                return;
            }

            if (!error) {
                const fetchUrl = this.options.shopUrl + '/mollie/components/store-card-token/' + this.options.customerId + '/' + token;

                // Store the token on the customer
                if (
                    !!fetchUrl
                    && !!this._confirmForm
                ) {
                    fetch(fetchUrl, {headers: {'Content-Type': 'application/json; charset=utf-8'}})
                        .then(() => {
                            // Add token to the form
                            const tokenInput = document.getElementById('cardToken');
                            tokenInput.setAttribute('value', token);

                            this._confirmForm.submit();
                        })
                        .catch(() => {
                            this._confirmForm.submit()
                        });
                }
            }
        }
    }
}
