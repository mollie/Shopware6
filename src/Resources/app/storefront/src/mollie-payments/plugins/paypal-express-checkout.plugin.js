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
        csrfToken: null,
    }

    init() {
        this._client = new HttpClient();
        this._registerEvents();
        console.log(123);
    }

    update() {
        this._registerEvents();
    }

    _onClick(event) {
        const data = this._getRequestData();

        ElementLoadingIndicatorUtil.create(this.el);

        this._client.post(this.options.route, JSON.stringify(data), content => this._parseRequest(JSON.parse(content)));
    }
    _parseRequest(data) {
        console.log(data);

        ElementLoadingIndicatorUtil.remove(this.el);
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
}
