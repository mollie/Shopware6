// eslint-disable-next-line no-undef
const {Application} = Shopware;

export default class MollieRefundsGrid {


    /**
     *
     * @returns {[{property: string, label: string, align: string},{property: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},null,null,null,null,null]}
     */
    buildColumns() {

        const app = Application.getApplicationRoot();

        return [
            {
                property: 'amount.value',
                label: app.$tc('mollie-payments.refund-manager.refunds.grid.columns.amount'),
                width: '120px',
                align: 'right',
            },
            {
                property: 'status',
                label: app.$tc('mollie-payments.refund-manager.refunds.grid.columns.status'),
                width: '150px',
            },
            {
                property: 'description',
                label: app.$tc('mollie-payments.refund-manager.refunds.grid.columns.description'),
            },
            {
                property: 'createdAt',
                label: app.$tc('mollie-payments.refund-manager.refunds.grid.columns.date'),
                width: '100px',
            },
        ];
    }

}
