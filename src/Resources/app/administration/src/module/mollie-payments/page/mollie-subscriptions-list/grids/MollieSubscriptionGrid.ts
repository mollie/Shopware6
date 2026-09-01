const { Application } = Shopware;

export default class MollieSubscriptionGrid {
    buildColumns(): any[] {
        const app = Application.getApplicationRoot();

        if (!app) {
            return [];
        }

        return [
            {
                property: 'createdAt',
                label: app.$tc('mollie-payments.subscriptions.list.columns.createdAt'),
                allowResize: true,
            },
            {
                property: 'customerId',
                label: app.$tc('mollie-payments.subscriptions.list.columns.customer'),
                allowResize: true,
            },
            {
                property: 'description',
                label: app.$tc('mollie-payments.subscriptions.list.columns.description'),
                allowResize: true,
            },
            {
                property: 'status',
                label: app.$tc('mollie-payments.subscriptions.list.columns.status'),
                allowResize: true,
                sortable: true,
            },
            {
                property: 'amount',
                label: app.$tc('mollie-payments.subscriptions.list.columns.amount'),
                allowResize: true,
                align: 'right',
            },
            {
                property: 'nextPaymentAt',
                label: app.$tc('mollie-payments.subscriptions.list.columns.nextPaymentAt'),
                allowResize: true,
            },
            {
                property: 'lastRemindedAt',
                label: app.$tc('mollie-payments.subscriptions.list.columns.prePaymentReminder'),
                allowResize: true,
            },
            {
                property: 'canceledAt',
                dataIndex: 'canceledAt',
                label: app.$tc('mollie-payments.subscriptions.list.columns.canceledAt'),
                visible: true,
            },
        ];
    }
}
