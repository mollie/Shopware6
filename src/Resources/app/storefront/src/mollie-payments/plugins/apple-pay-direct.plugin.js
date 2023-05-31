import Plugin from '@shopware-storefront-sdk/plugin-system/plugin.class';
import HttpClient from '../services/HttpClient';

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

        me.client = new HttpClient();

        // register our off-canvas listener
        // we need to re-init all apple pay button
        // once the offcanvas is loaded (lazy) into the DOM
        const elementOffcanvas = document.querySelector('[data-offcanvas-cart]');
        if (elementOffcanvas instanceof HTMLElement) {
            const pluginOffCanvas = window.PluginManager.getPluginInstanceFromElement(elementOffcanvas, 'OffCanvasCart');
            pluginOffCanvas.$emitter.subscribe('offCanvasOpened', me.onOffCanvasOpened.bind(me));
        }

        // now update our current page
        this.initCurrentPage();
    }

    /**
     *
     */
    onOffCanvasOpened() {
        // as soon as the offcanvas is loaded
        // we refresh our current page, because
        // there might be a new apple pay button
        this.initCurrentPage();
    }

    /**
     *
     */
    initCurrentPage() {

        const me = this;

        // we might have wrapping containers
        // that also need to be hidden -> they might have different margins or other things
        const applePayContainers = document.querySelectorAll('.js-apple-pay-container');
        // of course, also grab our real buttons
        const applePayButtons = document.querySelectorAll('.js-apple-pay');


        if (!window.ApplePaySession || !window.ApplePaySession.canMakePayments()) {
            // hide our wrapping Apple Pay containers
            // to avoid any wrong margins being displayed
            if (applePayContainers) {
                for (let i = 0; i < applePayContainers.length; i++) {
                    applePayContainers[i].style.display = 'none';
                }
            }
            return;
        }


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
        me.client.get(
            shopUrl + '/mollie/apple-pay/available',
            data => {
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
            }
        );
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
        const mode = form.querySelector('input[name="mode"]').value;

        // this helps us to figure out if we are in
        // "product" mode to purchase a single product, or in "cart" mode
        // to just purchase the current cart with Apple Pay Direct.
        const isProductMode = (mode === 'productMode');

        if (isProductMode) {
            // our fallback is quantity 1
            var quantity = 1;

            // if we have our sQuantity dropdown, use that quantity when adding the product
            var quantitySelects = document.getElementsByClassName('product-detail-quantity-select')
            if (quantitySelects.length > 0) {
                quantity = quantitySelects[0].value;
            }

            // also try our Shopware 6.5 selector
            const sw65Selector = 'lineItems[' + productId + '][quantity]';
            quantitySelects = document.getElementsByName(sw65Selector)
            if (quantitySelects.length > 0) {
                quantity = quantitySelects[0].value;
            }

            me.addProductToCart(productId, quantity, shopUrl);
        }

        var session = me.createApplePaySession(isProductMode, countryCode, currency, shopUrl);
        session.begin();
    }

    /**
     *
     * @param id
     * @param quantity
     * @param shopSlug
     */
    addProductToCart(id, quantity, shopSlug) {
        this.client.post(
            shopSlug + '/mollie/apple-pay/add-product',
            JSON.stringify({
                'id': id,
                'quantity': quantity,
            })
        )
    }

    /**
     *
     * @param isProductMode
     * @param country
     * @param currency
     * @param shopSlug
     * @returns {ApplePaySession}
     */
    createApplePaySession(isProductMode, country, currency, shopSlug) {

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
            me.client.post(
                shopSlug + '/mollie/apple-pay/validate',
                JSON.stringify({
                    validationUrl: event.validationURL,
                }),
                (validationData) => {
                    const data = JSON.parse(validationData.session);
                    session.completeMerchantValidation(data);
                },
                () => {
                    session.abort();
                }
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
                },
                () => {
                    session.abort();
                }
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
                },
                () => {
                    session.abort();
                }
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
            me.finishPayment(shopSlug + '/mollie/apple-pay/start-payment', paymentToken, event.payment);
        };

        session.oncancel = function () {

            // if we are in product mode
            // we should restore our original cart
            if (isProductMode) {
                me.client.post(shopSlug + '/mollie/apple-pay/restore-cart');
            }
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
        const createInput = function (name, val) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = val;

            return input;
        }

        const form = document.createElement('form');
        form.action = checkoutURL;
        form.method = 'POST';

        // add billing data
        form.insertAdjacentElement('beforeend', createInput('email', payment.shippingContact.emailAddress));
        form.insertAdjacentElement('beforeend', createInput('lastname', payment.shippingContact.familyName));
        form.insertAdjacentElement('beforeend', createInput('firstname', payment.shippingContact.givenName));
        form.insertAdjacentElement('beforeend', createInput('street', payment.shippingContact.addressLines[0]));
        form.insertAdjacentElement('beforeend', createInput('postalCode', payment.shippingContact.postalCode));
        form.insertAdjacentElement('beforeend', createInput('city', payment.shippingContact.locality));
        form.insertAdjacentElement('beforeend', createInput('countryCode', payment.shippingContact.countryCode));
        // also add our payment token
        form.insertAdjacentElement('beforeend', createInput('paymentToken', paymentToken));

        document.body.insertAdjacentElement('beforeend', form);

        form.submit();
    }

    /**
     *
     * @param button
     * @returns string
     */
    getShopUrl(button) {
        // get sales channel base URL
        // so that our shop slug is correctly
        let shopSlug = button.getAttribute('data-shop-url');

        // remove trailing slash if existing
        if (shopSlug.substr(-1) === '/') {
            shopSlug = shopSlug.substr(0, shopSlug.length - 1);
        }

        return shopSlug;
    }

}
