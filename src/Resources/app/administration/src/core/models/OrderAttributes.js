import CreditcardAttributes from './CreditcardAttributes';

export default class OrderAttributes {
    /**
     *
     * @param orderEntity
     */
    constructor(orderEntity) {
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
            transactions.forEach(function (transaction) {
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

    /**
     *
     * @returns {boolean}
     */
    isMollieOrder() {
        return this._isMolliePayments;
    }

    /**
     *
     * @returns {null|CreditcardAttributes|*}
     */
    getCreditCardAttributes() {
        return this._creditCardAttributes;
    }

    /**
     *
     * @returns {*}
     */
    getOrderId() {
        return this._orderId;
    }

    /**
     *
     * @returns {string|*}
     */
    getPaymentId() {
        return this._paymentId;
    }

    /**
     *
     * @returns {string|*|null}
     */
    getMollieID() {
        if (this.getOrderId() !== '') {
            return this.getOrderId();
        }

        if (this.getPaymentId() !== '') {
            return this.getPaymentId();
        }

        return null;
    }

    /**
     *
     * @returns {boolean}
     */
    isSubscription() {
        return this.getSwSubscriptionId() !== '';
    }

    /**
     *
     * @returns {string|*}
     */
    getSwSubscriptionId() {
        return this._swSubscriptionId;
    }

    /**
     *
     * @returns {string}
     */
    getPaymentRef() {
        return this._paymentRef;
    }

    /**
     *
     * @param value
     * @returns {string}
     * @private
     */
    _convertString(value) {
        if (value === undefined || value === null) {
            return '';
        }

        return String(value);
    }

    /**
     *
     * @param values
     * @returns {string}
     * @private
     */
    _firstNonEmpty(...values) {
        for (let i = 0; i < values.length; i++) {
            const str = this._convertString(values[i]);
            if (str !== '') {
                return str;
            }
        }

        return '';
    }
}
