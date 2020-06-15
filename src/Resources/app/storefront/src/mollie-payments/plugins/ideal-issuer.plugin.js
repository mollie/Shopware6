import Plugin from 'src/plugin-system/plugin.class';

export default class MollieIDealIssuer extends Plugin {
    init() {
        // get container
        const container = document.querySelector('div.mollie-ideal-issuer');

        if (
            container !== undefined
            && container !== null
        ) {
            let shopUrl = container.getAttribute('data-shop-url');
            //let issuer = container.getAttribute('data-issuer');
            const iDealIssuer = document.querySelector('#iDealIssuer');

            if (shopUrl.substr(-1) === '/') {
                shopUrl = shopUrl.substr(0, shopUrl.length - 1);
            }

            // Elements
            const customerId = container.getAttribute('data-customer-id');
            const paymentForm = document.querySelector('#confirmPaymentForm');
            const submitButton = document.querySelector('#confirmPaymentForm button[type="submit"]');
            const radioInputs = document.querySelectorAll('#confirmPaymentForm input[type="radio"]');
            const iDealRadioInput = document.querySelector('#confirmPaymentForm input[type="radio"].ideal');

            // Event helpers
            const showIssuers = () => {
                if (
                    iDealRadioInput === undefined
                    || iDealRadioInput.checked === false
                ) {
                    container.classList.add('d-none');
                } else {
                    container.classList.remove('d-none');
                }
            };

            const disableForm = () => {
                if (submitButton !== null) {
                    submitButton.disabled = true;
                }
            };

            showIssuers();

            radioInputs.forEach((element) => {
                element.addEventListener('change', () => {
                    showIssuers();
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
                    iDealRadioInput === undefined
                    || iDealRadioInput === null
                    || iDealRadioInput.checked === false
                    || iDealIssuer === undefined
                    || iDealIssuer === null
                ) {
                    paymentForm.submit();
                }

                if (
                    iDealRadioInput !== undefined
                    && iDealRadioInput !== null
                    && iDealRadioInput.checked === true
                    && iDealIssuer !== undefined
                    && iDealIssuer !== null
                ) {
                    const fetchUrl = shopUrl + '/mollie/ideal/store-issuer/' + customerId + '/' + iDealIssuer.value;

                    // Store the token on the customer
                    fetch(fetchUrl, { headers: { 'Content-Type': 'application/json; charset=utf-8' } })
                        .then(paymentForm.submit())
                        .catch(paymentForm.submit());
                }
            });
        }
    }
}