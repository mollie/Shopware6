export default class PluginConfiguration {

    constructor() {

        this._subscriptionIndicator = false;
        this._mollieFailureMode = false;
        this._creditCardComponents = false;
        this._applePayDirectEnabled = false;
        this._paypalExpressRestrictions = [];
    }


    getSubscriptionIndicator() {
        return this._subscriptionIndicator;
    }

    setSubscriptionIndicator(value) {
        this._subscriptionIndicator = value;
    }

    getMollieFailureMode() {
        return this._mollieFailureMode;
    }

    setMollieFailureMode(value) {
        this._mollieFailureMode = value;
    }

    getCreditCardComponents() {
        return this._creditCardComponents;
    }

    setCreditCardComponents(value) {
        this._creditCardComponents = value;
    }

    getApplePayDirectEnabled() {
        return this._applePayDirectEnabled;
    }

    setApplePayDirectEnabled(value) {
        this._applePayDirectEnabled = value;
    }


    getPaypalExpressRestrictions() {
        return this._paypalExpressRestrictions;
    }

    setPaypalExpressRestrictions(value) {
        this._paypalExpressRestrictions = value;
    }

}