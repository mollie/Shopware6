export default class OrderRefundGridBuilder {

    /**
     *
     * @returns {[{property: string, label: string, align: string},{property: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},null,null,null,null,null]}
     */
    buildColumns() {
        return [
            {
                label: 'Item',
                property: 'shopware.label',
                align: 'left',
            },
            {
                label: 'Product Number',
                property: 'shopware.productNumber',
                align: 'left',
            },
            {
                label: 'Unit Price',
                property: 'shopware.unitPrice',
                width: '90px',
                align: 'right',
            },
            {
                label: 'Quantity',
                property: 'shopware.quantity',
                width: '50px',
                align: 'right',
            },
            {
                label: 'Refunded',
                property: 'refunded',
                width: '50px',
                align: 'right',
            },
            {
                label: 'Refund',
                property: 'inputQuantity',
                width: '140px',
                align: 'center',
            },
            {
                label: 'Total',
                property: 'shopware.totalPrice',
                width: '110px',
                align: 'right',
            },
            {
                label: 'Refund',
                property: 'inputAmount',
                width: '150px',
                align: 'center',
            },
            {
                label: '',
                property: 'inputConsiderPromotion',
                align: 'center',
            },
            {
                label: 'Reset Stock',
                property: 'inputStock',
                width: '135px',
                align: 'center',
            },
        ];
    }

}
