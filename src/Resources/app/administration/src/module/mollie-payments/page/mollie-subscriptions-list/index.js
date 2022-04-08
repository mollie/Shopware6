import template from './mollie-subscriptions-list.html.twig';
import './mollie-subscriptions-list.scss';
import MollieSubscriptionGrid from './grids/MollieSubscriptionGrid';

// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

// eslint-disable-next-line no-undef
const {Criteria} = Shopware.Data;


Component.register('mollie-subscriptions-list', {
    template,

    inject: [
        'systemConfigApiService',
        'MolliePaymentsSubscriptionService',
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder'),
    ],

    data() {
        return {
            isLoading: true,
            // -------------------------------------
            systemConfig: null,
            customerRepository: null,
            // -------------------------------------
            subscriptions: null,
            // -------------------------------------
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            naturalSorting: true,
            showHelp: false,
        }
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {

        repoSubscriptions() {
            return this.repositoryFactory.create('mollie_subscription');
        },

        /**
         *
         * @returns {null|*}
         */
        prePaymentReminderEmail() {
            if (!this.systemConfig) {
                return null;
            }

            if (this.systemConfig['MolliePayments.config.prePaymentReminderEmail'] !== undefined) {
                return this.systemConfig['MolliePayments.config.prePaymentReminderEmail'];
            }

            return null;
        },

        /**
         *
         * @returns {*}
         */
        totalSubscriptions() {
            return this.subscriptions.length;
        },

    },

    methods: {

        /**
         *
         * @returns {[{allowResize: boolean, dataIndex: string, property: string, label},{allowResize: boolean, dataIndex: string, property: string, label},{allowResize: boolean, property: string, label},{allowResize: boolean, property: string, label},{allowResize: boolean, property: string, label},null,null,null]|*}
         */
        gridColumns() {
            const grid = new MollieSubscriptionGrid();
            return grid.buildColumns();
        },

        /**
         *
         */
        getList() {

            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'createdAt';

            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            criteria.addAssociation('customer');

            // eslint-disable-next-line no-undef
            this.repoSubscriptions.search(criteria, Shopware.Context.api).then((result) => {
                this.subscriptions = result;
                this.isLoading = false;
            });
        },

        // ---------------------------------------------------------------------------------------------------------
        // <editor-fold desc="EVENTS">
        // ---------------------------------------------------------------------------------------------------------

        /**
         * @param item
         */
        btnCancel_Click(item) {
            this.MolliePaymentsSubscriptionService
                .cancel({
                    id: item.id,
                })
                .then((response) => {
                    if (response.success) {
                        this.createNotificationSuccess({
                            message: this.$tc('mollie-payments.subscriptions.list.columns.action.success'),
                        });
                        this.showRefundModal = true;

                        // reload our list
                        this.getList();

                    } else {
                        this.createNotificationError({
                            message: this.$tc('mollie-payments.subscriptions.list.columns.action.error'),
                        });
                    }
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message,
                    });
                });
        },

        // ---------------------------------------------------------------------------------------------------------
        // </editor-fold>
        // ---------------------------------------------------------------------------------------------------------

        // ---------------------------------------------------------------------------------------------------------
        // <editor-fold desc="GRID">
        // ---------------------------------------------------------------------------------------------------------

        /**
         *
         * @param status
         * @returns {*}
         */
        statusTranslation(status) {

            if (status === '' || status === null) {
                status = 'pending';
            }

            if (['pending', 'active', 'canceled', 'suspended', 'completed'].includes(status)) {
                return this.$tc('mollie-payments.subscriptions.status.' + status);
            }

            return status;
        },

        /**
         *
         * @param status
         * @returns {string}
         */
        statusColor(status) {

            if (status === '' || status === null || status === 'pending') {
                return 'warning';
            }

            if (status === 'canceled' || status === 'suspended' || status === 'completed') {
                return 'neutral';
            }

            return 'success';
        },

        // ---------------------------------------------------------------------------------------------------------
        // </editor-fold>
        // ---------------------------------------------------------------------------------------------------------


    },
});
