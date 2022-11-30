import template from './mollie-subscriptions-detail.html.twig';
import './mollie-subscriptions-detail.scss';
import SubscriptionService from '../../../../core/service/subscription/subscription.service';

// eslint-disable-next-line no-undef
const {Component, Mixin, Application, ApiService} = Shopware;

// eslint-disable-next-line no-undef
const {Criteria} = Shopware.Data;


Component.register('mollie-subscriptions-detail', {
    template,

    inject: [
        'MolliePaymentsSubscriptionService',
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    data() {
        return {
            isLoading: true,
            allowPauseResume: false,
            allowSkip: false,
            // ------------------------------------------------
            showConfirmCancel: false,
            showConfirmPause: false,
            showConfirmResume: false,
            showConfirmSkip: false,
            // ------------------------------------------------
            subscription: null,
            history: [],
            // ------------------------------------------------
            customerFullName: '',
            translatedStatus: '',
            formattedCreateAt: '',
            formattedNextPaymentAt: '',
            formattedLastRemindedAt: '',
            formattedCanceledAt: '',
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
         * @returns {SubscriptionService}
         */
        subscriptionService() {
            // eslint-disable-next-line no-undef
            return new SubscriptionService(Application.getApplicationRoot());
        },

        subscriptionId() {
            return this.$route.params.id;
        },

        /**
         *
         * @returns {*}
         */
        isAclEditAllowed() {
            return this.acl.can('mollie_subscription:update');
        },

        /**
         *
         * @returns {*}
         */
        isAclCancelAllowed() {
            return this.acl.can('mollie_subscription_custom:cancel');
        },

        isCancellationAllowed() {
            if (this.subscription === null) {
                return false;
            }
            return this.subscriptionService.isCancellationAllowed(this.subscription.status);
        },

        isPauseAllowed() {
            if (this.subscription === null) {
                return false;
            }
            return this.subscriptionService.isPauseAllowed(this.subscription.status);
        },

        isResumeAllowed() {
            if (this.subscription === null) {
                return false;
            }
            return this.subscriptionService.isResumeAllowed(this.subscription.status);
        },

        isSkipAllowed() {
            if (this.subscription === null) {
                return false;
            }
            return this.subscriptionService.isSkipAllowed(this.subscription.status);
        },

        /**
         *
         * @returns {*}
         */
        cardTitleHistory() {
            return this.$tc('mollie-payments.subscriptions.detail.history.cardTitle') + ' (' + this.history.length + ')';
        },

    },

    created() {
        this.createdComponent();
    },

    methods: {

        /**
         *
         */
        createdComponent() {
            this.loadDetails();
        },

        /**
         *
         */
        loadDetails() {
            this.isLoading = true;

            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('id', this.subscriptionId));
            criteria.addAssociation('addresses');
            criteria.addAssociation('historyEntries');
            criteria.addAssociation('customer');

            // eslint-disable-next-line no-undef
            this.repoSubscriptions.search(criteria, Shopware.Context.api).then((result) => {
                this.subscription = result[0];

                this.customerFullName = this.subscription.customer.firstName + ' ' + this.subscription.customer.lastName;
                this.translatedStatus = this.statusTranslation(this.subscription.status);

                this.formattedCreateAt = this.getFormattedDate(this.subscription.createdAt)
                this.formattedNextPaymentAt = this.getFormattedDate(this.subscription.nextPaymentAt)
                this.formattedLastRemindedAt = this.getFormattedDate(this.subscription.lastRemindedAt)
                this.formattedCanceledAt = this.getFormattedDate(this.subscription.canceledAt)

                this.history = this.subscription.historyEntries;
                this.history.sort(function (a, b) {
                    return new Date(b.createdAt) - new Date(a.createdAt);
                });

                // translate our status values
                this.history.forEach((entry) => {
                    entry.statusFromTranslated = this.subscriptionService.getStatusTranslation(entry.statusFrom);
                    entry.statusToTranslated = this.subscriptionService.getStatusTranslation(entry.statusTo);
                })

                this.isLoading = false;
            });

            const systemConfig = ApiService.getByName('systemConfigApiService')
            systemConfig.getValues('MolliePayments').then(config => {
                this.allowPauseResume = config['MolliePayments.config.subscriptionsAllowPauseResume'];
                this.allowSkip = config['MolliePayments.config.subscriptionsAllowSkip'];
            });
        },

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

        /**
         *
         * @param date
         * @returns {string}
         */
        getFormattedDate(date) {
            if (date === null || date === '') {
                return '';
            }

            // eslint-disable-next-line no-undef
            const shopwareObj = Shopware;

            // starting with Shopware 6.4.10.0 we have a new dateWithUserTimezone function
            // before this, we just use the old one
            if (shopwareObj.Utils.format.dateWithUserTimezone) {
                const formattedDate = shopwareObj.Utils.format.dateWithUserTimezone(new Date(date));
                return formattedDate.toLocaleDateString() + ' ' + formattedDate.toLocaleTimeString();
            }

            return shopwareObj.Utils.format.date(new Date(date));
        },

        // ---------------------------------------------------------------------------------------------------------
        // <editor-fold desc="EVENTS">
        // ---------------------------------------------------------------------------------------------------------

        /**
         *
         */
        btnCancel_Click() {

            if (!this.isAclCancelAllowed) {
                return;
            }

            this.showConfirmCancel = true;
        },

        /**
         *
         */
        btnPause_Click() {
            this.showConfirmPause = true;
        },

        /**
         *
         */
        btnResume_Click() {
            this.showConfirmResume = true;
        },

        /**
         *
         */
        btnSkip_Click() {
            this.showConfirmSkip = true;
        },

        /**
         *
         */
        btnCloseAnyModal_Click() {
            this.showConfirmCancel = false;
            this.showConfirmPause = false;
            this.showConfirmResume = false;
            this.showConfirmSkip = false;
        },

        /**
         *
         */
        btnConfirmCancel_Click() {

            this.showConfirmCancel = false;

            if (!this.isAclCancelAllowed) {
                return;
            }

            this.MolliePaymentsSubscriptionService
                .cancel({
                    id: this.subscription.id,
                })
                .then((response) => {
                    if (response.success) {
                        this.loadDetails();
                        this.createNotificationSuccess({message: this.$tc('mollie-payments.subscriptions.alerts.cancelSuccess')});
                    } else {
                        this.createNotificationError({message: response.errors[0]});
                    }
                });
        },

        /**
         *
         */
        btnConfirmPause_Click() {
            this.showConfirmPause = false;

            this.MolliePaymentsSubscriptionService
                .pause({
                    id: this.subscription.id,
                })
                .then((response) => {
                    if (response.success) {
                        this.loadDetails();
                        this.createNotificationSuccess({message: this.$tc('mollie-payments.subscriptions.alerts.pauseSuccess')});
                    } else {
                        this.createNotificationError({message: response.errors[0]});
                    }
                });
        },

        /**
         *
         */
        btnConfirmResume_Click() {
            this.showConfirmResume = false;

            this.MolliePaymentsSubscriptionService
                .resume({
                    id: this.subscription.id,
                })
                .then((response) => {
                    if (response.success) {
                        this.loadDetails();
                        this.createNotificationSuccess({message: this.$tc('mollie-payments.subscriptions.alerts.resumeSuccess')});
                    } else {
                        this.createNotificationError({message: response.errors[0]});
                    }
                });
        },

        /**
         *
         */
        btnConfirmSkip_Click() {

            this.showConfirmSkip = false;

            this.MolliePaymentsSubscriptionService
                .skip({
                    id: this.subscription.id,
                })
                .then((response) => {
                    if (response.success) {
                        this.loadDetails();
                        this.createNotificationSuccess({message: this.$tc('mollie-payments.subscriptions.alerts.skipSuccess')});
                    } else {
                        this.createNotificationError({message: response.errors[0]});
                    }
                });
        },

        // ---------------------------------------------------------------------------------------------------------
        // </editor-fold>
        // ---------------------------------------------------------------------------------------------------------

    },

});
