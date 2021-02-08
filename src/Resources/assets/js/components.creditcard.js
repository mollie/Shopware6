document.addEventListener("DOMContentLoaded", function() {
    // get controller
    var mollieController = document.querySelector('div.mollie-components-controller');

    // remove existing mollie controller
    if (mollieController !== undefined && mollieController !== null) {
        mollieController.remove();
    }

    // get container
    var cardToken = document.querySelector('#cardHolder');

    if (cardToken !== undefined) {
        // Initialize Mollie Components instance
        const mollie = Mollie('[mollie_profile_id]', {
            locale: '[mollie_locale]',
            testmode: [mollie_testmode]
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
        const paymentForm = document.querySelector('#confirmPaymentForm');
        const submitButton = document.querySelector('#confirmPaymentForm button[type="submit"]');
        const customerId = document.querySelector('#confirmPaymentForm input[type="hidden"]#customerId');
        const creditCardRadioInput = document.querySelector('#confirmPaymentForm input[type="radio"].creditcard');

        // Event helpers
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

            if (
                creditCardRadioInput === undefined
                || creditCardRadioInput.checked === false
            ) {
                paymentForm.submit();
            }

            if (
                creditCardRadioInput !== undefined
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

                const fetchUrl = '[shop_url]/mollie/components/store-card-token/' + customerId.getAttribute('value') + '/' + token;

                console.log(fetchUrl);

                // Store the token on the customer
                fetch(fetchUrl, { headers: { "Content-Type": "application/json; charset=utf-8" }})
                    .then(res => res.json())
                    .then(response => {})
                    .catch(err => {});

                // Add token to the form
                const tokenInput = document.getElementById('cardToken');
                tokenInput.setAttribute('value', token);

                // Re-submit form to the server
                paymentForm.submit();
            }
        });
    }
});