import CreditcardAttributes from './CreditcardAttributes';

export default class OrderAttributes {
    private readonly _orderId: string;
    private readonly _paymentId: string;
    private readonly _swSubscriptionId: string;
    private readonly _creditCardAttributes: CreditcardAttributes | null;
    private readonly _paymentRef: string | null;
    private readonly _isMolliePayments: boolean;
    customFields: Record<string, any> | null | undefined;

    constructor(orderEntity: any) {
        this._orderId = '';
        this._paymentId = '';
        this._swSubscriptionId = '';
        this._creditCardAttributes = null;
        this._paymentRef = null;
        this._isMolliePayments = false;
        if (!orderEntity) {
            return;
        }

        const transactions = orderEntity.transactions;

        if (!transactions) {
            return;
        }

        let latestTransaction = typeof transactions.first === 'function' ? transactions.first() : transactions[0];

        if (!latestTransaction) {
            return;
        }

        transactions.forEach((transaction: any) => {
            if (transaction.createdAt > latestTransaction.createdAt) {
                latestTransaction = transaction;
            }
        });

        const paymentMethod = latestTransaction.paymentMethod;
        // customFields of payment_method are translatable, so the raw property is empty for every
        // language without its own translation row. "translated" holds the resolved fallback chain.
        const paymentMethodCustomFields = paymentMethod?.translated?.customFields ?? paymentMethod?.customFields;
        const isMolliePayments = paymentMethodCustomFields?.mollie_payment_method_name ?? null;

        if (!isMolliePayments) {
            return;
        }
        this._isMolliePayments = true;

        const txMollie = latestTransaction?.customFields?.mollie_payments ?? {};
        this._orderId = this._convertString(txMollie['orderId']);
        this._paymentId = this._convertString(txMollie['id']);

        this.customFields = orderEntity.customFields;

        if (this.customFields === null || this.customFields === undefined) {
            return;
        }

        if (this.customFields.mollie_payments === undefined || this.customFields.mollie_payments === null) {
            return;
        }

        const mollieData = this.customFields.mollie_payments;

        this._orderId = this._firstNonEmpty(mollieData['order_id'], mollieData['orderId'], this._orderId);
        this._paymentId = this._firstNonEmpty(mollieData['payment_id'], mollieData['id'], this._paymentId);
        this._swSubscriptionId = this._convertString(mollieData['swSubscriptionId']);
        this._paymentRef = this._firstNonEmpty(
            mollieData['third_party_payment_id'],
            mollieData['thirdPartyPaymentId'],
            this._paymentRef,
        );
        this._creditCardAttributes = new CreditcardAttributes(mollieData);
    }

    isMollieOrder(): boolean {
        return this._isMolliePayments;
    }

    getCreditCardAttributes(): CreditcardAttributes | null {
        return this._creditCardAttributes;
    }

    getOrderId(): string {
        return this._orderId;
    }

    getPaymentId(): string {
        return this._paymentId;
    }

    getMollieID(): string | null {
        if (this.getOrderId() !== '') {
            return this.getOrderId();
        }

        if (this.getPaymentId() !== '') {
            return this.getPaymentId();
        }

        return null;
    }

    isSubscription(): boolean {
        return this.getSwSubscriptionId() !== '';
    }

    getSwSubscriptionId(): string {
        return this._swSubscriptionId;
    }

    getPaymentRef(): string | null {
        return this._paymentRef;
    }

    private _convertString(value: any): string {
        if (value === undefined || value === null) {
            return '';
        }

        return String(value);
    }

    /**
     * Returns the first of the given values that converts to a non-empty string.
     * Used to read a custom field that exists under more than one key.
     */
    private _firstNonEmpty(...values: any[]): string {
        for (const value of values) {
            const str = this._convertString(value);
            if (str !== '') {
                return str;
            }
        }

        return '';
    }
}
