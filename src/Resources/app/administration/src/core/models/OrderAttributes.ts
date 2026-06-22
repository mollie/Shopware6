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

        if (orderEntity === null) {
            return;
        }

        const transactions = orderEntity.transactions;
        let latestTransaction = transactions?.first();

        if (transactions.length > 1) {
            transactions.forEach((transaction: any) => {
                if (transaction.createdAt > latestTransaction.createdAt) {
                    latestTransaction = transaction;
                }
            });
        }

        if (!latestTransaction) {
            return;
        }

        const isMolliePayments = latestTransaction.paymentMethod?.customFields?.mollie_payment_method_name ?? null;

        if (!isMolliePayments) {
            return;
        }
        this._isMolliePayments = true;

        this._paymentId = latestTransaction?.customFields?.mollie_payments?.id ?? '';

        this.customFields = orderEntity.customFields;

        if (this.customFields === null || this.customFields === undefined) {
            return;
        }

        if (this.customFields.mollie_payments === undefined || this.customFields.mollie_payments === null) {
            return;
        }

        const mollieData = this.customFields.mollie_payments;

        this._orderId = this._convertString(mollieData['order_id']);
        this._paymentId = this._convertString(mollieData['payment_id']);
        this._swSubscriptionId = this._convertString(mollieData['swSubscriptionId']);
        this._paymentRef = this._convertString(mollieData['third_party_payment_id']);
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
}
