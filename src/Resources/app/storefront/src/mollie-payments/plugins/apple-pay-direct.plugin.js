import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';

export default class MollieApplePayDirect extends Plugin {
    _data = {
        form: null,
        selectedProduct: null,
        shippingContact: null,
        shippingMethodId: null,
        cartAmount: 0.0,
        cartToken: '',
        csrfTokenAuthorize: '',
        csrfTokenShippingMethods: '',
        currency: '',
        shippingAmount: 0.0
    };

    init() {
        let me = this;

        // eslint-disable-next-line no-undef
        me._client = new HttpClient(window.accessKey, window.contextToken);

        // eslint-disable-next-line no-undef
        if (window.ApplePaySession && location.protocol === 'https:') {
            let applePayAvailablePromise = this.isApplePayAvailable();

            applePayAvailablePromise.then(function (data) {
                if (data.available !== undefined && data.available === true) {
                    me.enableApplePayButtons();
                }
            });
        }
    }

    totalAmount() {
        return this._data.cartAmount + this._data.shippingAmount;
    }

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

    enableApplePayButtons() {
        let me = this;
        let applePayButtons = document.querySelectorAll('.js-apple-pay');

        if (applePayButtons.length) {
            applePayButtons.forEach(function (item) {
                // Remove display none
                item.classList.remove('d-none');

                // Bind payment request
                item.addEventListener('click', function (e) {
                    e.preventDefault();

                    me.clearNotification();

                    me._data.form = item.parentNode;

                    let csrfTokens = me._data.form.querySelectorAll('#mollie-apd-csrf input[name="_mollie_csrf_token"]');

                    if (csrfTokens.length > 1) {
                        me._data.csrfTokenAuthorize = csrfTokens[0].value;
                        me._data.csrfTokenShippingMethods = csrfTokens[1].value;
                    }

                    let productId = me._data.form.querySelector('input[name="id"]').value;
                    let productName = me._data.form.querySelector('input[name="name"]').value;
                    let productPrice = me._data.form.querySelector('input[name="price"]').value;
                    let countryCode = me._data.form.querySelector('input[name="countryCode"]').value;
                    let currency = me._data.form.querySelector('input[name="currency"]').value;

                    let productPricePromise = me.getProductPrice(productId);

                    me._data.cartAmount = productPrice;
                    me._data.currency = currency;

                    me.createPaymentRequest(
                        'product',
                        countryCode,
                        currency,
                        productName
                    );

                    productPricePromise.then(function (product) {
                        me._data.selectedProduct = product.data;
                        me._data.cartAmount = me._data.selectedProduct.price;
                    });
                });
            });
        }
    }

    // eslint-disable-next-line no-unused-vars
    createPaymentRequest(type, countryCode, currencyCode, label) {
        let me = this;

        let request = {
            countryCode: countryCode,
            currencyCode: currencyCode,
            supportedNetworks: ["amex", "maestro", "masterCard", "visa", "vPay"],
            merchantCapabilities: ['supports3DS'],
            requiredShippingContactFields: ["name", "postalAddress", "phone", "email"],
            total: {label: label, amount: this._data.cartAmount}
        };

        // eslint-disable-next-line no-undef
        let session = new ApplePaySession(3, request);

        session.onvalidatemerchant = function (event) {
            let validationPromise = me.performValidation(event.validationURL);

            validationPromise
                .then(function (merchantSession) {
                    try {
                        session.completeMerchantValidation(merchantSession);
                    } catch (e) {
                        me.displayNotification(e.message, session);
                    }
                })
                .catch((reason => {
                    me.displayNotification(reason, session);
                }));
        };

        session.onshippingcontactselected = function (event) {
            // Store the shipping contact for later use
            me._data.shippingContact = event.shippingContact;

            // Get the country code
            if (me._data.shippingContact.countryCode !== undefined) {
                countryCode = me._data.shippingContact.countryCode;
            }

            // eslint-disable-next-line no-undef
            let status = ApplePaySession.STATUS_SUCCESS;
            let shippingMethodsPromise = me.getShippingMethods(countryCode);

            shippingMethodsPromise
                .then(function (shippingMethods) {
                    if (
                        shippingMethods.error !== undefined
                        && shippingMethods.error !== null
                    ) {
                        me.displayNotification(shippingMethods.error, session);
                    } else {
                        if (shippingMethods.length) {
                            me._data.shippingMethodId = shippingMethods[0].identifier;
                            me._data.shippingAmount = shippingMethods[0].amount;
                        }

                        let total = {
                            type: 'final',
                            label: 'Total amount',
                            amount: me.totalAmount()
                        };

                        let lineItems = [
                            {
                                type: 'final',
                                label: 'Subtotal',
                                amount: me._data.cartAmount
                            },
                            {
                                type: 'final',
                                label: 'Shipping costs',
                                amount: me._data.shippingAmount
                            }
                        ];

                        // Update shipping amount
                        let shippingPromise = me.getShippingAmount();

                        shippingPromise.then(function (shippingCosts) {
                            me._data.cartToken = shippingCosts.cartToken;
                            me._data.shippingMethodId = shippingCosts.shippingMethod.id;
                            me._data.shippingAmount = shippingCosts.totalPrice;
                        }).catch((reason => {
                            me.displayNotification(reason, session);
                        }));

                        try {
                            session.completeShippingContactSelection(status, shippingMethods, total, lineItems);
                        } catch (e) {
                            me.displayNotification(e.message, session);
                        }
                    }
                })
                .catch((reason => {
                    me.displayNotification(reason, session);
                }));
        };

        session.onshippingmethodselected = function (event) {
            // Get the shipping method id
            me._data.shippingMethodId = event.shippingMethod.identifier;

            // Get shipping amount
            let shippingPromise = me.getShippingAmount();

            shippingPromise
                .then(function (shippingCosts) {
                    me._data.cartToken = shippingCosts.cartToken;
                    me._data.shippingAmount = shippingCosts.totalPrice;

                    // eslint-disable-next-line no-undef
                    let status = ApplePaySession.STATUS_SUCCESS;

                    let total = {
                        type: 'final',
                        label: 'Total amount',
                        amount: me.totalAmount()
                    };

                    let lineItems = [
                        {
                            type: 'final',
                            label: 'Subtotal',
                            amount: me._data.cartAmount
                        },
                        {
                            type: 'final',
                            label: 'Shipping costs',
                            amount: me._data.shippingAmount
                        }
                    ];

                    try {
                        session.completeShippingMethodSelection(status, total, lineItems);
                    } catch (e) {
                        me.displayNotification(e.message, session);
                    }
                })
                .catch((reason => {
                    me.displayNotification(reason, session);
                }));
        };

        session.onpaymentmethodselected = function () {
            let total = {
                type: 'final',
                label: 'Total amount',
                amount: me.totalAmount()
            };

            let lineItems = [
                {
                    type: 'final',
                    label: 'Subtotal',
                    amount: me._data.cartAmount
                },
                {
                    type: 'final',
                    label: 'Shipping costs',
                    amount: me._data.shippingAmount
                }
            ];

            try {
                session.completePaymentMethodSelection(total, lineItems);
            } catch (e) {
                me.displayNotification(e.message, session);
            }
        };

        session.onpaymentauthorized = function (event) {
            let paymentPromise = me.sendPaymentToken(event.payment);

            paymentPromise
                .then(function (data) {
                    let status;
                    let redirectUrl;

                    if (
                        data.errors !== undefined
                        && data.errors !== null
                        && data.errors.length > 0
                    ) {
                        // eslint-disable-next-line no-undef
                        status = ApplePaySession.STATUS_FAILURE;

                        // Display the error message
                        let message = '';

                        data.errors.forEach(function (error) {
                            message += error + '<br />';
                        });

                        me.displayNotification(message, session);
                    } else if (
                        data.redirectUrl !== undefined
                        && data.redirectUrl !== null
                        && data.redirectUrl !== ''
                    ) {
                        // eslint-disable-next-line no-undef
                        status = ApplePaySession.STATUS_SUCCESS;
                        redirectUrl = data.redirectUrl;
                    }

                    try {
                        session.completePayment(status);
                    } catch (e) {
                        me.displayNotification(e.message, session);
                    }

                    if (!!redirectUrl) {
                        document.location = redirectUrl;
                    }
                })
                .catch((reason => {
                    me.displayNotification(reason, session);
                }));
        };

        session.oncancel = function () {
            // session is cancled
        };

        session.begin();
    }

    performValidation(validationUrl) {
        return new Promise(function (resolve, reject) {
            fetch('/mollie/apple-pay/validate?validationUrl=' + validationUrl)
                .then(response => response.json())
                .then(data => resolve(data))
                // eslint-disable-next-line no-unused-vars
                .catch((error) => {
                    reject();
                });
        });
    }

    sendPaymentToken(payment) {
        let me = this;
        let postData = {
            paymentToken: JSON.stringify(payment.token),
            shippingContact: JSON.stringify(payment.shippingContact),
            currency: me._data.currency,
            customer: me._data.shippingContact,
            productId: me._data.selectedProduct.id,
            shippingMethodId: me._data.shippingMethodId,
            cartAmount: me._data.cartAmount,
            cartToken: me._data.cartToken,
            shippingAmount: me._data.shippingAmount,
            totalAmount: me.totalAmount(),
            _csrf_token: me._data.csrfTokenAuthorize
        };

        return new Promise(function (resolve) {
            me._client.post('/mollie/apple-pay/authorize', JSON.stringify(postData), response => resolve(JSON.parse(response)));
        });
    }

    getProductPrice(productId) {
        return new Promise(function (resolve, reject) {
            fetch('/mollie/apple-pay/product/' + productId + '/price')
                .then(response => response.json())
                .then(data => resolve(data))
                // eslint-disable-next-line no-unused-vars
                .catch((error) => {
                    reject();
                });
        });
    }

    getShippingAmount() {
        let me = this;
        return new Promise(function (resolve, reject) {
            fetch('/mollie/apple-pay/shipping-costs/' + me._data.shippingMethodId + '/' + me._data.selectedProduct.id)
                .then(response => response.json())
                .then(data => resolve(data))
                // eslint-disable-next-line no-unused-vars
                .catch((error) => {
                    reject();
                });
        })
    }

    getShippingMethods(countryCode) {
        let me = this;
        let postData = {
            countryCode: countryCode,
            _csrf_token: me._data.csrfTokenShippingMethods
        };

        return new Promise(function (resolve) {
            me._client.post('/mollie/apple-pay/shipping-methods', JSON.stringify(postData), response => resolve(JSON.parse(response)));
        });
    }

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

    clearNotification() {
        let flashBagsContainer = document.querySelector('div.flashbags.container');

        if (flashBagsContainer !== undefined) {
            flashBagsContainer.innerHTML = '';
        }
    }
}
