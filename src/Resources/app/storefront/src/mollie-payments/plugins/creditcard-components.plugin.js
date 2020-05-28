import Plugin from 'src/plugin-system/plugin.class';

export default class MollieCreditCardComponents extends Plugin {
    init() {
        // get controller
        let mollieController = document.querySelector('div.mollie-components-controller');

        // remove existing mollie controller
        if (mollieController !== undefined && mollieController !== null) {
            mollieController.remove();
        }

        // get container
        let container = document.querySelector('div.mollie-components-credit-card');
        let cardToken = document.querySelector('#cardHolder');

        if (
            container !== undefined
            && container !== null
            && cardToken !== undefined
            && cardToken !== null
        ) {
            let locale = container.getAttribute('data-locale');
            let profileId = container.getAttribute('data-profile-id');
            let shopUrl = container.getAttribute('data-shop-url');
            let testMode = container.getAttribute('data-test-mode');

            if (shopUrl.substr(-1) === '/') {
                shopUrl = shopUrl.substr(0, shopUrl.length - 1);
            }

            // Initialize Mollie Components instance
            // eslint-disable-next-line no-undef
            const mollie = Mollie(profileId, {
                locale: locale,
                testmode: testMode
            });

            // Default properties
            const properties = {
                styles: {
                    base: {
                        backgroundColor: '#fff',
                        fontSize: '14px',
                        padding: '10px 10px',
                        '::placeholder': {
                            color: 'rgba(68, 68, 68, 0.2)',
                        }
                    },
                    valid: {
                        color: '#090',
                    },
                    invalid: {
                        backgroundColor: '#fff1f3',
                    },
                }
            };

            const cardHolder = {
                name: "cardHolder",
                id: "#cardHolder",
                errors: "cardHolderError"
            };
            const cardNumber = {
                name: "cardNumber",
                id: "#cardNumber",
                errors: "cardNumberError"
            };
            const expiryDate = {
                name: "expiryDate",
                id: "#expiryDate",
                errors: "expiryDateError"
            };
            const verificationCode = {
                name: "verificationCode",
                id: "#verificationCode",
                errors: "verificationCodeError"
            };

            const inputs = [cardHolder, cardNumber, expiryDate, verificationCode];

            // Elements
            const customerId = container.getAttribute('data-customer-id');
            const paymentForm = document.querySelector('#confirmPaymentForm');
            const submitButton = document.querySelector('#confirmPaymentForm button[type="submit"]');
            const radioInputs = document.querySelectorAll('#confirmPaymentForm input[type="radio"]');
            const creditCardRadioInput = document.querySelector('#confirmPaymentForm input[type="radio"].creditcard');

            // Event helpers
            const showComponents = () => {
                if (
                    creditCardRadioInput === undefined
                    || creditCardRadioInput.checked === false
                ) {
                    container.classList.add('d-none');
                } else {
                    container.classList.remove('d-none');
                }
            };

            const setFocus = (componentName, isFocused) => {
                const element = document.querySelector(componentName);
                element.classList.toggle("is-focused", isFocused);
            };

            const disableForm = () => {
                if (submitButton !== null) {
                    submitButton.disabled = true;
                }
            };

            const enableForm = () => {
                if (submitButton !== null) {
                    submitButton.disabled = false;
                }
            };

            showComponents();

            radioInputs.forEach((element) => {
                element.addEventListener('change', () => {
                    showComponents();
                });
            });

            // Create inputs
            inputs.forEach((element, index, arr) => {
                const component = mollie.createComponent(element.name, properties);
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
                    setFocus(`${element.id}`, true);
                });
                component.addEventListener('blur', () => {
                    setFocus(`${element.id}`, false);
                });
            });

            // Submit handler
            paymentForm.addEventListener('submit', async event => {
                event.preventDefault();
                disableForm();

                // Fallback for submitting the form
                setTimeout(function () {
                    paymentForm.submit();
                }, 2000);

                if (
                    creditCardRadioInput === undefined
                    || creditCardRadioInput === null
                    || creditCardRadioInput.checked === false
                ) {
                    paymentForm.submit();
                }

                if (
                    creditCardRadioInput !== undefined
                    && creditCardRadioInput !== null
                    && creditCardRadioInput.checked === true
                ) {
                    // Reset possible form errors
                    const verificationErrors = document.getElementById(`${verificationCode.errors}`);
                    verificationErrors.textContent = '';

                    // Get a payment token
                    const {token, error} = await mollie.createToken();

                    if (error) {
                        enableForm();
                        verificationErrors.textContent = error.message;
                        return;
                    }

                    const fetchUrl = shopUrl + '/mollie/components/store-card-token/' + customerId + '/' + token;

                    // Store the token on the customer
                    fetch(fetchUrl, { headers: { "Content-Type": "application/json; charset=utf-8" } })
                        .then(() => {
                            // Add token to the form
                            const tokenInput = document.getElementById('cardToken');
                            tokenInput.setAttribute('value', token);
                            paymentForm.submit();
                        })
                        .catch(paymentForm.submit());
                }
            });
        }
    }
}