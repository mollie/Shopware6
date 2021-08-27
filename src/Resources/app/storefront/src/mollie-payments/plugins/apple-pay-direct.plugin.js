import Plugin from 'src/plugin-system/plugin.class';

export default class MollieApplePayDirect extends Plugin {

    /**
     *
     * @type {number}
     */
    APPLE_PAY_VERSION = 3;


    /**
     *
     */
    init() {
        let me = this;

        if (!window.ApplePaySession || !window.ApplePaySession.canMakePayments()) {
            return;
        }

        let applePayAvailablePromise = this.isApplePayAvailable();

        applePayAvailablePromise.then(function (data) {

            if (data.available === undefined || data.available === false) {
                return;
            }

            let applePayButtons = document.querySelectorAll('.js-apple-pay');

            if (applePayButtons.length > 0) {

                applePayButtons.forEach(function (button) {
                    // Remove display none
                    button.classList.remove('d-none');
                    // remove previous handlers (just in case)
                    button.removeEventListener("click", me.onButtonClick.bind(me));
                    // add click event handlers
                    button.addEventListener('click', me.onButtonClick.bind(me));
                });
            }
        });
    }


    /**
     *
     * @returns {Promise<unknown>}
     */
    isApplePayAvailable() {
        return new Promise(function (resolve, reject) {
            fetch('/mollie/apple-pay/available')
                .then(response => response.json())
                .then(data => resolve(data))
                // eslint-disable-next-line no-unused-vars
                .catch((error) => {
                    reject();
                });
        });
    }

    /**
     *
     * @param event
     * @param me
     */
    onButtonClick(event) {

        event.preventDefault();

        let me = this;

        const button = event.target;
        const form = button.parentNode;

        let productId = form.querySelector('input[name="id"]').value;
        let countryCode = form.querySelector('input[name="countryCode"]').value;
        let currency = form.querySelector('input[name="currency"]').value;

        // our fallback is quantity 1
        var quantity = 1;

        // if we have our sQuantity dropdown, use
        // that quantity when adding the product
        var quantitySelects = document.getElementsByClassName('product-detail-quantity-select')
        if (quantitySelects.length > 0) {
            quantity = quantitySelects[0].value;
        }

        me.addProductToCart(productId, quantity);

        var session = me.createApplePaySession(countryCode, currency);
        session.begin();
    }

    /**
     *
     * @param id
     * @param quantity
     */
    addProductToCart(id, quantity) {
        fetch('/mollie/apple-pay/add-product',
            {
                method: 'POST',
                body: JSON.stringify({
                    'id': id,
                    'quantity': quantity,
                })
            }
        );
    }

    /**
     *
     * @param label
     * @param amount
     * @param country
     * @param currency
     * @returns {ApplePaySession}
     */
    createApplePaySession(country, currency) {

        let me = this;

        var request = {
            countryCode: country,
            currencyCode: currency,
            requiredShippingContactFields: [
                "name",
                "email",
                "postalAddress"
            ],
            supportedNetworks: [
                'amex',
                'maestro',
                'masterCard',
                'visa',
                'vPay'
            ],
            merchantCapabilities: ['supports3DS', 'supportsEMV', 'supportsCredit', 'supportsDebit'],
            total: {
                label: '',
                amount: 0
            }
        };

        // eslint-disable-next-line no-undef
        const session = new ApplePaySession(this.APPLE_PAY_VERSION, request);

        session.onvalidatemerchant = function (event) {

            fetch('/mollie/apple-pay/validate',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        validationUrl: event.validationURL
                    })
                })
                .then(function (response) {
                    return response.json();
                })
                .then(function (validationData) {
                    const data = JSON.parse(validationData.session);
                    session.completeMerchantValidation(data);
                })
                .catch(() => {
                    session.abort();
                });
        };

        session.onshippingcontactselected = function (event) {

            var countryCode = '';

            if (event.shippingContact.countryCode !== undefined) {
                countryCode = event.shippingContact.countryCode;
            }

            fetch('/mollie/apple-pay/shipping-methods',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        countryCode: countryCode,
                    })
                })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        session.completeShippingContactSelection(
                            // eslint-disable-next-line no-undef
                            ApplePaySession.STATUS_SUCCESS,
                            data.shippingmethods,
                            data.cart.total,
                            data.cart.items
                        );
                    } else {
                        session.completeShippingContactSelection(
                            // eslint-disable-next-line no-undef
                            ApplePaySession.STATUS_FAILURE,
                            [],
                            {
                                label: "",
                                amount: 0,
                                pending: true
                            },
                            []
                        );
                    }
                })
                .catch(() => {
                    session.abort();
                });
        };

        session.onshippingmethodselected = function (event) {

            fetch('/mollie/apple-pay/set-shipping',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        identifier: event.shippingMethod.identifier
                    })
                })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        session.completeShippingMethodSelection(
                            // eslint-disable-next-line no-undef
                            ApplePaySession.STATUS_SUCCESS,
                            data.cart.total,
                            data.cart.items
                        );
                    } else {
                        session.completeShippingMethodSelection(
                            // eslint-disable-next-line no-undef
                            ApplePaySession.STATUS_FAILURE,
                            {
                                label: "",
                                amount: 0,
                                pending: true
                            },
                            []
                        );
                    }
                })
                .catch(() => {
                    session.abort();
                });
        };

        session.onpaymentauthorized = function (event) {
            var paymentToken = event.payment.token;
            paymentToken = JSON.stringify(paymentToken);

            // complete the session and notify the
            // devices and the system that everything worked
            // eslint-disable-next-line no-undef
            session.completePayment(ApplePaySession.STATUS_SUCCESS);

            // now finish our payment by filling a form
            // and submitting it along with our payment token
            me.finishPayment('/mollie/apple-pay/start-payment', paymentToken, event.payment);
        };

        session.oncancel = function () {
            fetch('/mollie/apple-pay/restore-cart', {method: 'POST'});
        };

        return session;
    }

    /**
     *
     * @param checkoutURL
     * @param paymentToken
     * @param payment
     */
    finishPayment(checkoutURL, paymentToken, payment) {
        var $form;
        var createField = function (name, val) {
            return $('<input>', {
                type: 'hidden',
                name: name,
                value: val
            });
        };

        $form = $('<form>', {
            action: checkoutURL,
            method: 'POST'
        });

        // add billing data
        createField('email', payment.shippingContact.emailAddress).appendTo($form);
        createField('lastname', payment.shippingContact.familyName).appendTo($form);
        createField('firstname', payment.shippingContact.givenName).appendTo($form);
        createField('street', payment.shippingContact.addressLines[0]).appendTo($form);
        createField('postalCode', payment.shippingContact.postalCode).appendTo($form);
        createField('city', payment.shippingContact.locality).appendTo($form);
        createField('countryCode', payment.shippingContact.countryCode).appendTo($form);
        // also add our payment token
        createField('paymentToken', paymentToken).appendTo($form);

        $form.appendTo($('body'));

        $form.submit();
    }

    /**
     *
     * @param message
     * @param session
     * @param type
     */
    displayNotification(message, session, type) {
        let flashBagsContainer = document.querySelector('div.flashbags.container');

        if (type === undefined || type === null) {
            type = 'danger';
        }

        if (flashBagsContainer !== undefined) {
            let html = `<div role="alert" class="alert alert-${type}"><div class="alert-content-container"><div class="alert-content">${message}</div></div></div>`;
            flashBagsContainer.innerHTML = html;
            window.scrollTo(0, 0);
        }
    }

    /**
     *
     */
    clearNotification() {
        let flashBagsContainer = document.querySelector('div.flashbags.container');

        if (flashBagsContainer !== undefined) {
            flashBagsContainer.innerHTML = '';
        }
    }

}
