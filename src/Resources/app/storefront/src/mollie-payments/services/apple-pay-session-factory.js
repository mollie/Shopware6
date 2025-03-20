import HttpClient from './http-client';

export default class ApplePaySessionFactory {
    /**
     *
     * @type {number}
     */
    APPLE_PAY_VERSION = 3;

    constructor() {
        this.client = new HttpClient();
    }

    /**
     *
     * @param isProductMode
     * @param country
     * @param currency
     * @param shopSlug
     * @param withPhone
     * @param dataProtection
     * @param clickedButton
     * @returns ApplePaySession
     */
    create(isProductMode, country, currency, withPhone, shopSlug, dataProtection, clickedButton) {
        const me = this;
        var shippingFields = ['name', 'email', 'postalAddress'];

        if (withPhone === 1) {
            shippingFields.push('phone');
        }
        let dataProtectionValue = false;
        if (dataProtection !== null) {
            dataProtectionValue = dataProtection.value;
        }

        var request = {
            countryCode: country,
            currencyCode: currency,
            requiredShippingContactFields: shippingFields,
            supportedNetworks: ['amex', 'maestro', 'masterCard', 'visa', 'vPay'],
            merchantCapabilities: ['supports3DS'],
            total: {
                label: '',
                amount: 0,
            },
        };

        // eslint-disable-next-line no-undef
        const session = new ApplePaySession(this.APPLE_PAY_VERSION, request);

        session.onvalidatemerchant = function (event) {
            me.client.post(
                shopSlug + '/mollie/apple-pay/validate',
                JSON.stringify({
                    validationUrl: event.validationURL,
                }),
                (validationData) => {
                    if (validationData.success === false) {
                        throw new Error('Validation failed for URL: ' + event.validationURL);
                    }
                    const data = JSON.parse(validationData.session);
                    session.completeMerchantValidation(data);
                },
                () => {
                    session.abort();
                },
            );
        };

        session.onshippingcontactselected = function (event) {
            var countryCode = '';

            if (event.shippingContact.countryCode !== undefined) {
                countryCode = event.shippingContact.countryCode;
            }

            me.client.post(
                shopSlug + '/mollie/apple-pay/shipping-methods',
                JSON.stringify({
                    countryCode: countryCode,
                }),
                (data) => {
                    if (data.success) {
                        session.completeShippingContactSelection(
                            // eslint-disable-next-line no-undef
                            ApplePaySession.STATUS_SUCCESS,
                            data.shippingmethods,
                            data.cart.total,
                            data.cart.items,
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
                            [],
                        );
                    }
                },
                () => {
                    session.abort();
                },
            );
        };

        session.onshippingmethodselected = function (event) {
            me.client.post(
                shopSlug + '/mollie/apple-pay/set-shipping',
                JSON.stringify({
                    identifier: event.shippingMethod.identifier,
                }),
                (data) => {
                    if (data.success) {
                        session.completeShippingMethodSelection(
                            // eslint-disable-next-line no-undef
                            ApplePaySession.STATUS_SUCCESS,
                            data.cart.total,
                            data.cart.items,
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
                            [],
                        );
                    }
                },
                () => {
                    session.abort();
                },
            );
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
            me.finishPayment(
                shopSlug + '/mollie/apple-pay/start-payment',
                paymentToken,
                event.payment,
                dataProtectionValue,
            );
            clickedButton.classList.remove('processed');
        };

        session.oncancel = function () {
            // if we are in product mode
            // we should restore our original cart
            if (isProductMode) {
                me.client.post(shopSlug + '/mollie/apple-pay/restore-cart');
            }
            clickedButton.classList.remove('processed');
        };

        return session;
    }

    /**
     *
     * @param checkoutURL
     * @param paymentToken
     * @param payment
     */
    finishPayment(checkoutURL, paymentToken, payment, dataProtectionValue) {
        const createInput = function (name, val) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = val;

            return input;
        };

        const form = document.createElement('form');
        form.action = checkoutURL;
        form.method = 'POST';

        let street = payment.shippingContact.addressLines[0];

        if (payment.shippingContact.addressLines.length > 1) {
            street += ' ' + payment.shippingContact.addressLines[1];
        }

        // add billing data
        form.insertAdjacentElement('beforeend', createInput('email', payment.shippingContact.emailAddress));
        form.insertAdjacentElement('beforeend', createInput('lastname', payment.shippingContact.familyName));
        form.insertAdjacentElement('beforeend', createInput('firstname', payment.shippingContact.givenName));
        form.insertAdjacentElement('beforeend', createInput('street', street));
        form.insertAdjacentElement('beforeend', createInput('postalCode', payment.shippingContact.postalCode));
        form.insertAdjacentElement('beforeend', createInput('city', payment.shippingContact.locality));
        form.insertAdjacentElement('beforeend', createInput('acceptedDataProtection', dataProtectionValue));

        if (payment.shippingContact.phoneNumber !== undefined && payment.shippingContact.phoneNumber.length > 0) {
            form.insertAdjacentElement('beforeend', createInput('phone', payment.shippingContact.phoneNumber));
        }

        form.insertAdjacentElement('beforeend', createInput('countryCode', payment.shippingContact.countryCode));
        // also add our payment token
        form.insertAdjacentElement('beforeend', createInput('paymentToken', paymentToken));

        document.body.insertAdjacentElement('beforeend', form);

        form.submit();
    }
}
