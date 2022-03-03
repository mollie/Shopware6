const { Application } = Shopware;


export default class MollieRefundsGridBuilder {

    /**
     *
     * @returns {[{property: string, label: string, align: string},{property: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},null,null,null,null,null]}
     */
    buildColumns() {
        return [
            {
                property: 'amount.value',
                label: Application.getApplicationRoot().$tc('mollie-payments.modals.refund.list.column.amount'),
                width: '120px',
                align: 'right',
            },
            {
                property: 'status',
                label: Application.getApplicationRoot().$tc('mollie-payments.modals.refund.list.column.status'),
                width: '150px',
            },
            {
                property: 'description',
                label: 'Description',
            },
            {
                property: 'createdAt',
                label: Application.getApplicationRoot().$tc('mollie-payments.modals.refund.list.column.date'),
                width: '100px',
            },
        ];
    }

}
