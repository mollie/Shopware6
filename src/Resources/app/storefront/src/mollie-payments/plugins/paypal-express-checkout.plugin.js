import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';

export default class MolliePaypalExpressCheckout extends Plugin {
    static options = {
        productId: null,
        productName: null,
        productPrice: null,
        currency: null,
        currencyId: null,
        countryCode: null,
        shippingMethodId: null,
        route: window.router['frontend.mollie.paypal-ecs.checkout'],
        availableRoute: window.router['frontend.mollie.paypal-ecs.available'],
        csrfToken: null,
    }

    init() {
        this._client = new HttpClient();
        this._available()
            .then(() => this.el.classList.remove('d-none'))
            .then(() => this._registerEvents());
    }

    update() {
        this._available().then(() => this._registerEvents());
    }

    _available() {
        const self = this;
        return new Promise((resolve) => {
            self._client.get(self.options.availableRoute, response => resolve(JSON.parse(response)))
        }).then(expressCheckout => {
            if (expressCheckout.isAvailable) {
                Promise.resolve();
            } else {
                Promise.reject();
            }
        })
    }

    _onClick() {
        ElementLoadingIndicatorUtil.create(this.el);

        this._checkout();
    }

    _checkout() {
        this._doCheckout()
            .catch((reason => {
                this.displayNotification(reason);
            }))
    }

    _doCheckout() {
        const data = this._getRequestData();
        const self = this;

        return new Promise(function (resolve) {
            self._client.post(self.options.route, JSON.stringify(data), response => resolve(JSON.parse(response)));
        }).then(data => {
            ElementLoadingIndicatorUtil.remove(self.el);

            let checkoutUrl;

            if (
                data.errors !== undefined
                && data.errors !== null
                && data.errors.length > 0
            ) {
                // Display the error message
                let message = '';

                data.errors.forEach(function (error) {
                    message += error + '<br />';
                });

                self.displayNotification(message);
            } else if (
                data.checkoutUrl !== undefined
                && data.checkoutUrl !== null
                && data.checkoutUrl !== ''
            ) {
                checkoutUrl = data.checkoutUrl;
            }

            if (!!checkoutUrl) {
                document.location = checkoutUrl;
            }
        })
    }

    _getRequestData() {
        const data = {
            productId: this.options.productId,
            productName: this.options.productName,
            productPrice: this.options.productPrice,
            currency: this.options.currency,
            currencyId: this.options.currencyId,
            countryCode: this.options.countryCode,
            shippingMethodId: this.options.shippingMethodId,
            location: location.toString(),
        };

        if (window.csrf.enabled && window.csrf.mode === 'twig') {
            data['_csrf_token'] = this.options.csrfToken;
        }

        return data;
    }

    _registerEvents() {
        const onClick = this._onClick.bind(this);
        this.el.removeEventListener('click', onClick);
        this.el.addEventListener('click', onClick);
    }


    displayNotification(message, type) {
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
