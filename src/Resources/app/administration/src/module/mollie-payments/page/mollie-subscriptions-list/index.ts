import template from './mollie-subscriptions-list.html.twig';
import './mollie-subscriptions-list.scss';
import MollieSubscriptionGrid from './grids/MollieSubscriptionGrid';
import SubscriptionService from '../../../../core/service/subscription/subscription.service';

const { Component, Mixin, Application, Filter } = Shopware;
const { Criteria } = Shopware.Data;

interface SubscriptionsListPage {
    isLoading: boolean;
    systemConfig: any;
    subscriptions: any;
    sortBy: string;
    sortDirection: string;
    naturalSorting: boolean;
    showHelp: boolean;
    searchConfigEntity: string;

    [key: string]: any;
}

const componentConfig: ThisType<SubscriptionsListPage> = {
    template,

    inject: ['systemConfigApiService', 'MolliePaymentsSubscriptionService', 'repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('listing'), Mixin.getByName('placeholder')],

    data() {
        return {
            isLoading: true,
            systemConfig: null,
            subscriptions: null,
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
        repoSubscriptions() {
            return this.repositoryFactory.create('mollie_subscription');
        },

        subscriptionService() {
            return new SubscriptionService(Application.getApplicationRoot());
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

        totalSubscriptions() {
            return this.subscriptions.length;
        },

        /**
         * Provide icon compatibility for 6.4. Shopware's compatibility mapping will be removed in 6.5
         * @see vendor/shopware/administration/Resources/app/administration/src/app/component/base/sw-icon/legacy-icon-mapping.js
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
        gridColumns() {
            const grid = new MollieSubscriptionGrid();

            return grid.buildColumns();
        },

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

            this.repoSubscriptions.search(criteria, Shopware.Context.api).then((result: any) => {
                this.subscriptions = result;
                this.isLoading = false;
            });
        },

        statusTranslation(status: string) {
            return this.subscriptionService.getStatusTranslation(status);
        },

        statusColor(status: string) {
            return this.subscriptionService.getStatusColor(status);
        },
    },
};

Component.register('mollie-subscriptions-list', componentConfig);
