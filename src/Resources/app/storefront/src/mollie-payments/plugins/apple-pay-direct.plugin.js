import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

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
        const me = this;

        if (!window.ApplePaySession || !window.ApplePaySession.canMakePayments()) {
            return;
        }


        const applePayButtons = document.querySelectorAll('.js-apple-pay');

        if (applePayButtons.length <= 0) {
            return;
        }

        // we start by fetching the shop url from the data attribute.
        // we need this as prefix for our ajax calls, so that we always
        // call the correct sales channel and its controllers.
        const button = applePayButtons[0];
        const shopUrl = me.getShopUrl(button);

        // verify if apple pay is even allowed
        // in our current sales channel
        const applePayAvailablePromise = this.isApplePayAvailable(shopUrl);

        applePayAvailablePromise.then(function (data) {

            if (data.available === undefined || data.available === false) {
                return;
            }

            applePayButtons.forEach(function (button) {
                // Remove display none
                button.classList.remove('d-none');
                // remove previous handlers (just in case)
                button.removeEventListener('click', me.onButtonClick.bind(me));
                // add click event handlers
                button.addEventListener('click', me.onButtonClick.bind(me));
            });
        });
    }


    /**
     *
     * @param shopUrl
     * @returns {Promise<unknown>}
     */
    isApplePayAvailable(shopUrl) {
        return new Promise(function (resolve, reject) {
            fetch(shopUrl + '/mollie/apple-pay/available')
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
     */
    onButtonClick(event) {

        event.preventDefault();

        const me = this;

        const button = event.target;
        const form = button.parentNode;

        const shopUrl = me.getShopUrl(button);

        const productId = form.querySelector('input[name="id"]').value;
        const countryCode = form.querySelector('input[name="countryCode"]').value;
        const currency = form.querySelector('input[name="currency"]').value;

        // our fallback is quantity 1
        var quantity = 1;

        // if we have our sQuantity dropdown, use
        // that quantity when adding the product
        var quantitySelects = document.getElementsByClassName('product-detail-quantity-select')
        if (quantitySelects.length > 0) {
            quantity = quantitySelects[0].value;
        }

        me.addProductToCart(productId, quantity, shopUrl);

        var session = me.createApplePaySession(countryCode, currency, shopUrl);
        session.begin();
    }

    /**
     *
     * @param id
     * @param quantity
     * @param shopSlug
     */
    addProductToCart(id, quantity, shopSlug) {
        fetch(shopSlug + '/mollie/apple-pay/add-product',
            {
                method: 'POST',
                body: JSON.stringify({
                    'id': id,
                    'quantity': quantity,
                }),
            }
        );
    }

    /**
     *
     * @param country
     * @param currency
     * @param shopSlug
     * @returns {ApplePaySession}
     */
    createApplePaySession(country, currency, shopSlug) {

        const me = this;

        var request = {
            countryCode: country,
            currencyCode: currency,
            requiredShippingContactFields: [
                'name',
                'email',
                'postalAddress',
            ],
            supportedNetworks: [
                'amex',
                'maestro',
                'masterCard',
                'visa',
                'vPay',
            ],
            merchantCapabilities: ['supports3DS'],
            total: {
                label: '',
                amount: 0,
            },
        };

        // eslint-disable-next-line no-undef
        const session = new ApplePaySession(this.APPLE_PAY_VERSION, request);

        session.onvalidatemerchant = function (event) {

            fetch(shopSlug + '/mollie/apple-pay/validate',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        validationUrl: event.validationURL,
                    }),
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

            fetch(shopSlug + '/mollie/apple-pay/shipping-methods',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        countryCode: countryCode,
                    }),
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
                                label: '',
                                amount: 0,
                                pending: true,
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

            fetch(shopSlug + '/mollie/apple-pay/set-shipping',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        identifier: event.shippingMethod.identifier,
                    }),
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
                                label: '',
                                amount: 0,
                                pending: true,
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
            me.finishPayment(shopSlug + '/mollie/apple-pay/start-payment', paymentToken, event.payment);
        };

        session.oncancel = function () {
            fetch(shopSlug + '/mollie/apple-pay/restore-cart', {method: 'POST'});
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
                value: val,
            });
        };

        $form = $('<form>', {
            action: checkoutURL,
            method: 'POST',
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
     * @param button
     * @returns string
     */
    getShopUrl(button) {
        // get sales channel base URL
        // so that our shop slug is correctly
        let shopSlug = DomAccess.getDataAttribute(button, 'data-shop-url');

        // remove trailing slash if existing
        if (shopSlug.substr(-1) === '/') {
            shopSlug = shopSlug.substr(0, shopSlug.length - 1);
        }

        return shopSlug;
    }

}
