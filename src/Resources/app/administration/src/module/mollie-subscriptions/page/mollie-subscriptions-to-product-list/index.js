import template from './mollie-subscriptions-to-product-list.html.twig';
import ShopwareOrderGrid from "../../../mollie-payments/components/mollie-refund-manager/grids/ShopwareOrderGrid";
import MollieSubscriptionGrid from "./grids/MollieSubscriptionGrid";

// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;
// eslint-disable-next-line no-undef
const {Criteria} = Shopware.Data;

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
            this.repository = this.repositoryFactory.create('mollie_subscription');
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
            const grid = new MollieSubscriptionGrid();
            return grid.buildColumns();
        },

        statusTransation(status) {
            if (['pending', 'active', 'canceled', 'suspended', 'completed'].includes(status)) {
                return this.$tc('mollie-subscriptions.subscriptionStatus.' + status);
            }

            return status;
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
