import template from './mollie-subscriptions-to-product-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('mollie-subscriptions-to-product-list', {
    template,

    inject: [
        'systemConfigApiService',
        'MolliePaymentsSubscriptionService',
        'repositoryFactory',
    ],

    data() {
        return {
            isLoading: true,
            repository: null,
            customerRepository: null,
            subscriptions: null,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            naturalSorting: true,
            showHelp: false,
            systemConfig: null,
        }
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        columns() {
            return this.getColumns();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.repository = this.repositoryFactory.create('mollie_subscription_to_product');
            this.getSubscriptions();
            this.systemConfigApiService.getValues('MolliePayments.config').then(configData => {
                this.systemConfig = configData;
            });
        },

        getSubscriptions() {
            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'createdAt';

            let criteria = new Criteria();
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            this.repository.search(criteria, Shopware.Context.api).then((result) => {
                this.subscriptions = result;
                this.isLoading = false;
            });
        },

        /**
         * @returns {[{allowResize: boolean, dataIndex: string, property: string, label}, {allowResize: boolean, dataIndex: string, property: string, label}, {allowResize: boolean, property: string, label}, {allowResize: boolean, property: string, label}, {allowResize: boolean, property: string, label}, null, null, null]}
         */
        getColumns() {
            return [{
                property: 'subscriptionId',
                dataIndex: 'subscriptionId',
                label: this.$tc('mollie-subscriptions.page.list.columns.subscription_id'),
                allowResize: true,
            }, {
                property: 'mollieCustomerId',
                dataIndex: 'mollieCustomerId',
                label: this.$tc('mollie-subscriptions.page.list.columns.customer_id'),
                allowResize: true,
            }, {
                property: 'salesChannelId',
                dataIndex: 'salesChannelId',
                label: this.$tc('mollie-subscriptions.page.list.columns.salesChannelId'),
                visible: false,
            }, {
                property: 'status',
                label: this.$tc('mollie-subscriptions.page.list.columns.status'),
                allowResize: true,
            }, {
                property: 'description',
                label: this.$tc('mollie-subscriptions.page.list.columns.description'),
                allowResize: true,
            }, {
                property: 'createdAt',
                label: this.$tc('mollie-subscriptions.page.list.columns.createdAt'),
                allowResize: true,
            }, {
                property: 'amount',
                label: this.$tc('mollie-subscriptions.page.list.columns.amount'),
                allowResize: true,
            }, {
                property: 'nextPaymentDate',
                label: this.$tc('mollie-subscriptions.page.list.columns.nextPaymentAt'),
                allowResize: true,
            }, {
                property: 'prePaymentReminder',
                label: this.$tc('mollie-subscriptions.page.list.columns.prePaymentReminder'),
                allowResize: true,
            }];
        },

        /**
         * @param item
         * @returns {Date}
         */
        prePaymentReminder(item) {
            var prePaymentReminderEmail = this.systemConfig['MolliePayments.config.prePaymentReminderEmail'];
            if (prePaymentReminderEmail != null && prePaymentReminderEmail) {
                var b = new Date(item.nextPaymentDate);
                var daysBeforeReminder = this.systemConfig['MolliePayments.config.daysBeforeReminder'];
                b.setDate(b.getDate() - daysBeforeReminder);
                return new Date(b);
            }
        },

        /**
         * @param item
         */
        onCancel(item) {
            this.MolliePaymentsSubscriptionService
                .cancel({
                    id: item.subscriptionId,
                    customerId: item.mollieCustomerId,
                    salesChannelId: item.salesChannelId,
                })
                .then((response) => {
                    if (response.success) {
                        this.createNotificationSuccess({
                            message: this.$tc('mollie-subscriptions.page.list.columns.action.success'),
                        });
                        this.showRefundModal = false;
                        this.getSubscriptions();
                    } else {
                        this.createNotificationError({
                            message: this.$tc('mollie-subscriptions.page.list.columns.action.error'),
                        });
                    }
                })
                .then(() => {
                    this.$emit('refund-cancelled');
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message,
                    });
                });
        },
    },
});
