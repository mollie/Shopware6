import template from './sw-customer-mollie-subscriptions.html.twig';
import SubscriptionService from '../../../../core/service/subscription/subscription.service';

const { Component, Application } = Shopware;

interface CustomerSubscriptionsView {
    isLoading: boolean;
    subscriptions: any[];

    [key: string]: any;
}

const componentConfig: ThisType<CustomerSubscriptionsView> = {
    template,

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    inject: ['MolliePaymentsSubscriptionService'],

    data() {
        return {
            isLoading: false,
            subscriptions: [],
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        columns() {
            return [
                { property: 'id', label: 'ID', sortable: true },
                {
                    property: 'status',
                    label: this.$tc('mollie-payments.subscriptions.list.columns.status'),
                    sortable: true,
                },
                { property: 'description', label: this.$tc('mollie-payments.subscriptions.list.columns.description') },
                {
                    property: 'startDate',
                    label: this.$tc('mollie-payments.subscriptions.list.columns.createdAt'),
                    sortable: true,
                },
                { property: 'interval', label: 'Interval', sortable: true },
                {
                    property: 'nextPaymentDate',
                    label: this.$tc('mollie-payments.subscriptions.list.columns.nextPaymentAt'),
                    sortable: true,
                },
                {
                    property: 'canceledAt',
                    label: this.$tc('mollie-payments.subscriptions.list.columns.canceledAt'),
                    sortable: true,
                },
                {
                    property: 'amount',
                    label: this.$tc('mollie-payments.subscriptions.list.columns.amount'),
                    sortable: true,
                },
            ];
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        subscriptionService() {
            return new SubscriptionService(Application.getApplicationRoot());
        },
    },

    watch: {
        async customer() {
            await this.createdComponent();
        },
    },

    methods: {
        async cancelSubscription(item: any) {
            this.isLoading = true;
            const response = await this.MolliePaymentsSubscriptionService.cancelByMollieId({
                mollieCustomerId: item.customerId,
                mollieSubscriptionId: item.id,
                mandateId: item.mandateId,
                salesChannelId: this.customer.salesChannelId,
            });

            const updatedSubscription = response.subscription;

            if (updatedSubscription !== undefined) {
                this.subscriptions.forEach((subscription: any, index: number) => {
                    if (subscription.id === updatedSubscription.id) {
                        this.subscriptions[index].status = updatedSubscription.status;
                    }
                });
            }

            this.isLoading = false;
        },

        async createdComponent() {
            this.isLoading = true;
            const response = await this.MolliePaymentsSubscriptionService.getUserSubscriptions({
                id: this.customer.id,
            });
            this.subscriptions = response.subscriptions;
            this.isLoading = false;
        },

        statusColor(status: string) {
            return this.subscriptionService.getStatusColor(status);
        },
    },
};

Component.register('sw-customer-mollie-subscriptions', componentConfig);
