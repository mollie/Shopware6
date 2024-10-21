import HttpClient from '../services/HttpClient';
import Plugin from '../Plugin';
import ApplePaySessionFactory from '../services/ApplePaySessionFactory';

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

        const pluginOffCanvasInstances = window.PluginManager.getPluginList().OffCanvasCart.get('instances');
        if (pluginOffCanvasInstances.length > 0) {
            const pluginOffCanvas = pluginOffCanvasInstances[0];
            pluginOffCanvas.$emitter.subscribe('offCanvasOpened', me.initCurrentPage.bind(me));
        }


        const submitForm = document.querySelector('#productDetailPageBuyProductForm');

        if (submitForm !== null) {
            this.checkSubmitButton(submitForm);
            submitForm.addEventListener('change', (event) => {
                this.checkSubmitButton(event.target.closest('form#productDetailPageBuyProductForm'));
                this.initCurrentPage();
            });
        }

        // now update our current page
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
                applePayContainers.forEach(function (container) {
                    container.style.display = 'none';
                    container.classList.add('d-none');
                });
            }
            return;
        }


        if (applePayButtons.length <= 0) {
            return;
        }

        // we start by fetching the shop url from the data attribute.
        // we need this as prefix for our ajax calls, so that we always
        // call the correct sales channel and its controllers.

        const shopUrl = me.getShopUrl(applePayButtons[0]);


        // verify if apple pay is even allowed
        // in our current sales channel
        me.client.get(
            shopUrl + '/mollie/apple-pay/available',
            data => {
                if (data.available === undefined || data.available === false) {
                    return;
                }

                applePayContainers.forEach(function (container) {
                    container.classList.remove('d-none');
                });

                applePayButtons.forEach(function (button) {

                    if (button.hasAttribute('disabled')) {
                        button.classList.add('d-none');
                        button.removeEventListener('click', me.onButtonClick);
                        return;
                    }
                    // Remove display none
                    button.classList.remove('d-none');
                    // remove previous handlers (just in case)
                    button.removeEventListener('click', me.onButtonClick);
                    // add click event handlers
                    button.addEventListener('click', me.onButtonClick);
                });
            }
        );
    }

    checkSubmitButton(form) {
        const buyButton = form.querySelector('.btn-buy');

        if (buyButton === null) {
            return;
        }

        const expressButtons = form.querySelectorAll('.mollie-express-button');

        if (expressButtons.length === 0) {
            return;
        }

        expressButtons.each(function(expressButton){
            if (expressButton.hasAttribute('disabled')) {
                expressButton.removeAttribute('disabled');
            }
            if (buyButton.hasAttribute('disabled')) {
                expressButton.setAttribute('disabled', 'disabled');
            }
        })
    }

    /**
     *
     * @param event
     */
    onButtonClick(event) {

        event.preventDefault();

        const button = event.target;
        const form = button.parentNode;

        // get sales channel base URL
        // so that our shop slug is correctly
        let shopSlug = button.getAttribute('data-shop-url');

        // remove trailing slash if existing
        if (shopSlug.substr(-1) === '/') {
            shopSlug = shopSlug.substr(0, shopSlug.length - 1);
        }

        const countryCode = form.querySelector('input[name="countryCode"]').value;
        const currency = form.querySelector('input[name="currency"]').value;
        const mode = form.querySelector('input[name="mode"]').value;
        const withPhone = parseInt(form.querySelector('input[name="withPhone"]').value);
        const dataProtection = form.querySelector('input[name="acceptedDataProtection"]');

        form.classList.remove('was-validated');

        if (dataProtection !== null) {
            const dataProtectionValue = dataProtection.checked ? 1: 0;
            form.classList.add('was-validated');

            dataProtection.classList.remove('is-invalid');
            if (dataProtectionValue === 0) {
                dataProtection.classList.add('is-invalid');
                return;
            }
        }

        // this helps us to figure out if we are in
        // "product" mode to purchase a single product, or in "cart" mode
        // to just purchase the current cart with Apple Pay Direct.
        const isProductMode = (mode === 'productMode');

        if (isProductMode) {

            let productForm = document.querySelector('#productDetailPageBuyProductForm');
            if (productForm === null) {
                productForm = button.closest('.product-box').querySelector('form');
            }


            const formData = new FormData(productForm);
            formData.delete('redirectTo');
            formData.append('isExpressCheckout', '1');


            fetch(productForm.action, {
                method: productForm.method,
                body: formData,
            });


        }
        const applePaySessionFactory = new ApplePaySessionFactory();
        const session = applePaySessionFactory.create(isProductMode, countryCode, currency, withPhone, shopSlug, dataProtection);
        session.begin();

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
