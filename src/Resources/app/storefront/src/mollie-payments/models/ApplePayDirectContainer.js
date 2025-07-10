export default class ApplePayDirectContainer {
    constructor(container, applePayButton) {
        this._countryCode = container.querySelector('input[name="countryCode"]').value;
        this._currency = container.querySelector('input[name="currency"]').value;
        this._mode = container.querySelector('input[name="mode"]').value;
        this._withPhone = parseInt(container.querySelector('input[name="withPhone"]').value);
        this._dataProtection = container.querySelector('input[name="acceptedDataProtection"]');
        this._isProductMode = this._mode === 'productMode';

        let shopSlug = applePayButton.getAttribute('data-shop-url');
        if (shopSlug.slice(-1) === '/') {
            shopSlug = shopSlug.slice(0, -1);
        }
        this._shopSlug = shopSlug;
    }

    isProductMode() {
        return this._isProductMode;
    }

    getCountryCode() {
        return this._countryCode;
    }

    getCurrency() {
        return this._currency;
    }

    getMode() {
        return this._mode;
    }

    getWithPhone() {
        return this._withPhone;
    }

    getDataProtection() {
        return this._dataProtection;
    }

    getShopSlug() {
        return this._shopSlug;
    }
}
