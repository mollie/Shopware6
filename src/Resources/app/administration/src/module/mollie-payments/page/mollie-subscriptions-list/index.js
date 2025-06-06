import template from './mollie-subscriptions-list.html.twig';
import './mollie-subscriptions-list.scss';
import MollieSubscriptionGrid from './grids/MollieSubscriptionGrid';
import SubscriptionService from '../../../../core/service/subscription/subscription.service';

// eslint-disable-next-line no-undef
const { Component, Mixin, Application, Filter } = Shopware;

// eslint-disable-next-line no-undef
const { Criteria } = Shopware.Data;

Component.register('mollie-subscriptions-list', {
    template,

    inject: ['systemConfigApiService', 'MolliePaymentsSubscriptionService', 'repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('listing'), Mixin.getByName('placeholder')],

    data() {
        return {
            isLoading: true,
            // -------------------------------------
            systemConfig: null,
            // -------------------------------------
            subscriptions: null,
            // -------------------------------------
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            naturalSorting: true,
            showHelp: false,
            searchConfigEntity: 'mollie_subscription',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        /**
         *
         * @returns {mollie_subscription}
         */
        repoSubscriptions() {
            return this.repositoryFactory.create('mollie_subscription');
        },

        /**
         *
         * @returns {SubscriptionService}
         */
        subscriptionService() {
            // eslint-disable-next-line no-undef
            return new SubscriptionService(Application.getApplicationRoot());
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

        /**
         * Provide icon compatibility for 6.4. Shopware's compatibility mapping will be removed in 6.5
         * @see vendor/shopware/administration/Resources/app/administration/src/app/component/base/sw-icon/legacy-icon-mapping.js
         * @returns {object}
         */
        compatibilityIcons() {
            const map = Component.getComponentRegistry();
            return {
                refresh: map.has('icons-regular-undo') ? 'regular-undo' : 'default-arrow-360-left',
            };
        },
        currencyFilter() {
            return Filter.getByName('currency');
        },

        dateFilter() {
            return Filter.getByName('date');
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
        async getList() {
            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'createdAt';

            let criteria = new Criteria();

            // Compatibility for 6.4.4, as admin search was improved in 6.4.5
            if ('addQueryScores' in this) {
                criteria = await this.addQueryScores(this.term, criteria);
            } else {
                criteria.setTerm(this.term);
            }

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            criteria.addAssociation('customer');
            criteria.addAssociation('currency');

            // eslint-disable-next-line no-undef
            this.repoSubscriptions.search(criteria, Shopware.Context.api).then((result) => {
                this.subscriptions = result;
                this.isLoading = false;
            });
        },

        // ---------------------------------------------------------------------------------------------------------
        // <editor-fold desc="GRID">
        // ---------------------------------------------------------------------------------------------------------

        /**
         *
         * @param status
         * @returns {*}
         */
        statusTranslation(status) {
            return this.subscriptionService.getStatusTranslation(status);
        },

        /**
         *
         * @param status
         * @returns {string}
         */
        statusColor(status) {
            return this.subscriptionService.getStatusColor(status);
        },

        // ---------------------------------------------------------------------------------------------------------
        // </editor-fold>
        // ---------------------------------------------------------------------------------------------------------
    },
});
