import template from './mollie-subscriptions-to-product-list.html.twig';

// eslint-disable-next-line no-undef
const { Component, Mixin } = Shopware;
// eslint-disable-next-line no-undef
const { Criteria } = Shopware.Data;

Component.register('mollie-subscriptions-to-product-list', {
    template,

    inject: [
        'systemConfigApiService',
        'MolliePaymentsSubscriptionService',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
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

        prePaymentReminderEmail() {
            if (!this.systemConfig) {
                return null;
            }

            if (this.systemConfig['MolliePayments.config.prePaymentReminderEmail'] !== undefined) {
                return this.systemConfig['MolliePayments.config.prePaymentReminderEmail'];
            }

            return null;
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

            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            // eslint-disable-next-line no-undef
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
            if (this.prePaymentReminderEmail != null && this.prePaymentReminderEmail) {
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
                        this.showRefundModal = true;
                        this.getSubscriptions();
                    } else {
                        this.createNotificationError({
                            message: this.$tc('mollie-subscriptions.page.list.columns.action.error'),
                        });
                    }
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message,
                    });
                });
        },
    },
});
