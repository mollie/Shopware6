export default class MollieRefundItemBuilder {


    /**
     *
     * @param lineItem
     * @returns {{shopware: {unitPrice, quantity, totalPrice: any, label, productNumber: string}, refundMode: string, refundQuantity: number, resetStock: boolean, refundAmount: number}}
     */
    build(lineItem) {

        var productNumber = '';

        if (lineItem.payload.productNumber) {
            productNumber = lineItem.payload.productNumber;
        }

        return {
            'shopware': {
                'id': lineItem.id,
                'label': lineItem.label,
                'unitPrice': lineItem.unitPrice,
                'quantity': lineItem.quantity,
                'totalPrice': lineItem.totalPrice,
                'productNumber': productNumber,
            },
            // refund mode: none, quantity, amount
            'refundMode': 'none',
            'refundQuantity': 0,
            'refundAmount': 0,
            'resetStock': 0,
            'refunded': 0,
        };
    }

}
