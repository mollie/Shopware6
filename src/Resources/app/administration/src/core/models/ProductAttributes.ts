export default class ProductAttributes {
    private readonly _voucherType: any;
    private readonly _subscriptionProduct: any;
    private readonly _subscriptionInterval: any;
    private readonly _subscriptionIntervalUnit: any;
    private readonly _subscriptionRepetition: any;

    constructor(productEntity: any) {
        this._voucherType = '';
        this._subscriptionProduct = '';
        this._subscriptionInterval = '';
        this._subscriptionIntervalUnit = '';
        this._subscriptionRepetition = '';

        if (productEntity === null) {
            return;
        }

        const customFields = productEntity.customFields;

        if (customFields === null || customFields === undefined) {
            return;
        }

        this._voucherType = customFields['mollie_payments_product_voucher_type'];

        this._subscriptionProduct = customFields['mollie_payments_product_subscription_enabled'];
        this._subscriptionInterval = customFields['mollie_payments_product_subscription_interval'];
        this._subscriptionIntervalUnit = customFields['mollie_payments_product_subscription_interval_unit'];
        this._subscriptionRepetition = customFields['mollie_payments_product_subscription_repetition'];
    }

    getVoucherType(): string {
        const stringType = this._voucherType + '';

        // we only allow values 1, 2, and 3
        // all other values are just empty
        if (stringType !== '0' && stringType !== '1' && stringType !== '2' && stringType !== '3') {
            return '';
        }

        return stringType;
    }

    isSubscriptionProduct(): boolean {
        const boolType = this._subscriptionProduct;

        if (!boolType) {
            return false;
        }

        return boolType;
    }

    getSubscriptionInterval(): any {
        return this._subscriptionInterval;
    }

    getSubscriptionIntervalUnit(): string {
        const stringType = this._subscriptionIntervalUnit + '';

        if (stringType !== 'days' && stringType !== 'weeks' && stringType !== 'months') {
            return '';
        }

        return stringType;
    }

    getSubscriptionRepetition(): any {
        return this._subscriptionRepetition;
    }

    toArray(originalFields: any): any {
        return originalFields;
    }

    hasData(): boolean {
        return this._voucherType !== '' || !!this._subscriptionProduct;
    }
}
