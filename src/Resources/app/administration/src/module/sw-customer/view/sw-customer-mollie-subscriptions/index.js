import template from './sw-customer-mollie-subscriptions.html.twig';

// eslint-disable-next-line no-undef
Shopware.Component.register('sw-customer-mollie-subscriptions', {
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
    },
    watch: {
        async customer() {
            await this.createdComponent();
        },
    },
    methods: {
        async cancelSubscription(item) {
            this.isLoading = true;
            const response = await this.MolliePaymentsSubscriptionService.cancelByMollieId({
                customerId: this.customer.id,
                mollieSubscriptionId: item.id,
            });
            item = response.subscription;
            this.subscriptions.forEach((subscription, index) => {
                if (subscription.id !== item.id) {
                    this.subscriptions[index] = item;
                }
            });

            this.isLoading = false;
        },

        async createdComponent() {
            this.isLoading = true;
            const response = await this.MolliePaymentsSubscriptionService.getUserSubscriptions({
                id: this.customer.id,
            });
            this.subscriptions = response.subscriptions;
            console.error(this.subscriptions);
            this.isLoading = false;
        },
    },
});
