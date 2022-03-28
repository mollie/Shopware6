// eslint-disable-next-line no-undef
const {Application} = Shopware;

export default class MollieSubscriptionGrid {


    /**
     *
     * @returns {[{property: string, label: string, align: string},{property: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},{property: string, width: string, label: string, align: string},null,null,null,null,null]}
     */
    buildColumns() {

        const app = Application.getApplicationRoot();

        return [{
            property: 'subscriptionId',
            dataIndex: 'subscriptionId',
            label: app.$tc('mollie-subscriptions.page.list.columns.subscription_id'),
            allowResize: true,
        }, {
            property: 'mollieCustomerId',
            dataIndex: 'mollieCustomerId',
            label: app.$tc('mollie-subscriptions.page.list.columns.customer_id'),
            allowResize: true,
        }, {
            property: 'salesChannelId',
            dataIndex: 'salesChannelId',
            label: app.$tc('mollie-subscriptions.page.list.columns.salesChannelId'),
            visible: false,
        }, {
            property: 'status',
            label: app.$tc('mollie-subscriptions.page.list.columns.status'),
            allowResize: true,
        }, {
            property: 'description',
            label: app.$tc('mollie-subscriptions.page.list.columns.description'),
            allowResize: true,
        }, {
            property: 'createdAt',
            label: app.$tc('mollie-subscriptions.page.list.columns.createdAt'),
            allowResize: true,
        }, {
            property: 'amount',
            label: app.$tc('mollie-subscriptions.page.list.columns.amount'),
            allowResize: true,
        }, {
            property: 'nextPaymentDate',
            label: app.$tc('mollie-subscriptions.page.list.columns.nextPaymentAt'),
            allowResize: true,
        }, {
            property: 'prePaymentReminder',
            label: app.$tc('mollie-subscriptions.page.list.columns.prePaymentReminder'),
            allowResize: true,
        }];
    }

}
