import template from './mollie-subscriptions-detail.html.twig';
import './mollie-subscriptions-detail.scss';
import SubscriptionService from '../../../../core/service/subscription/subscription.service';
import VersionCompare from '../../../../core/service/utils/version-compare.utils';

const { Component, Mixin, Application, ApiService, Filter } = Shopware;
const { Criteria } = Shopware.Data;

interface SubscriptionsDetailPage {
    isLoading: boolean;
    allowPauseResume: boolean;
    allowSkip: boolean;
    showConfirmCancel: boolean;
    showConfirmPause: boolean;
    showConfirmResume: boolean;
    showConfirmSkip: boolean;
    subscription: any;
    history: any[];
    customerFullName: string;
    translatedStatus: string;
    formattedCreateAt: string;
    formattedNextPaymentAt: string;
    formattedLastRemindedAt: string;
    formattedCanceledAt: string;
    editStatus: string;
    editMollieId: string;
    editOrderId: string;
    showConfirmEdit: boolean;
    isSavingEdit: boolean;

    [key: string]: any;
}

const componentConfig: ThisType<SubscriptionsDetailPage> = {
    template,

    inject: ['MolliePaymentsSubscriptionService', 'repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    data() {
        return {
            isLoading: true,
            allowPauseResume: false,
            allowSkip: false,
            showConfirmCancel: false,
            showConfirmPause: false,
            showConfirmResume: false,
            showConfirmSkip: false,
            subscription: null,
            history: [],
            customerFullName: '',
            translatedStatus: '',
            formattedCreateAt: '',
            formattedNextPaymentAt: '',
            formattedLastRemindedAt: '',
            formattedCanceledAt: '',
            editStatus: '',
            editMollieId: '',
            editOrderId: '',
            showConfirmEdit: false,
            isSavingEdit: false,
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

        subscriptionId() {
            return this.$route.params.id;
        },

        isAclEditAllowed() {
            return this.acl.can('mollie_subscription:update');
        },

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

        cardTitleHistory() {
            return (
                this.$tc('mollie-payments.subscriptions.detail.history.cardTitle') + ' (' + this.history.length + ')'
            );
        },

        dateFilter() {
            return Filter.getByName('date');
        },

        editWarningVariant() {
            return new VersionCompare().greaterOrEqual(Shopware.Context.app.config.version, '6.7.0.0')
                ? 'critical'
                : 'error';
        },

        statusOptions() {
            return ['pending', 'active', 'suspended', 'completed', 'canceled', 'paused', 'resumed', 'skipped'].map(
                (status: string) => ({ value: status, label: this.statusTranslation(status) }),
            );
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadDetails();
        },

        loadDetails() {
            this.isLoading = true;

            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('id', this.subscriptionId));
            criteria.addAssociation('addresses');
            criteria.addAssociation('historyEntries');
            criteria.addAssociation('customer');
            criteria.addAssociation('currency');

            this.repoSubscriptions.search(criteria, Shopware.Context.api).then((result: any) => {
                this.subscription = result[0];

                this.customerFullName =
                    this.subscription.customer.firstName + ' ' + this.subscription.customer.lastName;
                this.translatedStatus = this.statusTranslation(this.subscription.status);

                this.formattedCreateAt = this.getFormattedDate(this.subscription.createdAt);
                this.formattedNextPaymentAt = this.getFormattedDate(this.subscription.nextPaymentAt);
                this.formattedLastRemindedAt = this.getFormattedDate(this.subscription.lastRemindedAt);
                this.formattedCanceledAt = this.getFormattedDate(this.subscription.canceledAt);

                this.editStatus = this.subscription.status;
                this.editMollieId = this.subscription.mollieId ?? '';
                this.editOrderId = this.subscription.orderId;

                this.history = this.subscription.historyEntries;
                this.history.sort(
                    (a: any, b: any) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime(),
                );

                // translate our status values
                this.history.forEach((entry: any) => {
                    entry.statusFromTranslated = this.subscriptionService.getStatusTranslation(entry.statusFrom);
                    entry.statusToTranslated = this.subscriptionService.getStatusTranslation(entry.statusTo);
                });

                this.isLoading = false;
            });

            const systemConfig = ApiService.getByName('systemConfigApiService');
            systemConfig.getValues('MolliePayments').then((config: any) => {
                this.allowPauseResume = config['MolliePayments.config.subscriptionsAllowPauseResume'];
                this.allowSkip = config['MolliePayments.config.subscriptionsAllowSkip'];
            });
        },

        statusTranslation(status: string) {
            return this.subscriptionService.getStatusTranslation(status);
        },

        statusColor(status: string) {
            return this.subscriptionService.getStatusColor(status);
        },

        getFormattedDate(date: string | null): string {
            if (date === null || date === '') {
                return '';
            }

            const formattedDate = Shopware.Utils.format.dateWithUserTimezone(new Date(date));

            return formattedDate.toLocaleDateString() + ' ' + formattedDate.toLocaleTimeString();
        },

        btnCancel_Click() {
            if (!this.isAclCancelAllowed) {
                return;
            }

            this.showConfirmCancel = true;
        },

        btnPause_Click() {
            this.showConfirmPause = true;
        },

        btnResume_Click() {
            this.showConfirmResume = true;
        },

        btnSkip_Click() {
            this.showConfirmSkip = true;
        },

        btnCloseAnyModal_Click() {
            this.showConfirmCancel = false;
            this.showConfirmPause = false;
            this.showConfirmResume = false;
            this.showConfirmSkip = false;
            this.showConfirmEdit = false;
        },

        btnSaveEdit_Click() {
            this.showConfirmEdit = true;
        },

        btnConfirmEdit_Click() {
            this.showConfirmEdit = false;

            if (!this.isAclEditAllowed) {
                return;
            }

            this.isSavingEdit = true;

            this.MolliePaymentsSubscriptionService.edit({
                id: this.subscription.id,
                status: this.editStatus,
                mollieId: this.editMollieId,
                orderId: this.editOrderId,
            })
                .then((response: any) => {
                    if (!response.success) {
                        this.createNotificationError({ message: response.errors[0] });
                        return;
                    }

                    this.loadDetails();
                    this.createNotificationSuccess({
                        message: this.$tc('mollie-payments.subscriptions.alerts.editSuccess'),
                    });
                })
                .finally(() => {
                    this.isSavingEdit = false;
                });
        },

        btnConfirmCancel_Click() {
            this.showConfirmCancel = false;

            if (!this.isAclCancelAllowed) {
                return;
            }

            this._runSubscriptionAction('cancel', 'mollie-payments.subscriptions.alerts.cancelSuccess');
        },

        btnConfirmPause_Click() {
            this.showConfirmPause = false;
            this._runSubscriptionAction('pause', 'mollie-payments.subscriptions.alerts.pauseSuccess');
        },

        btnConfirmResume_Click() {
            this.showConfirmResume = false;
            this._runSubscriptionAction('resume', 'mollie-payments.subscriptions.alerts.resumeSuccess');
        },

        btnConfirmSkip_Click() {
            this.showConfirmSkip = false;
            this._runSubscriptionAction('skip', 'mollie-payments.subscriptions.alerts.skipSuccess');
        },

        /**
         * Runs a subscription action (cancel/pause/resume/skip) against the API and reloads
         * the detail on success. Shared by all confirm handlers to avoid duplication.
         */
        _runSubscriptionAction(action: string, successMessage: string) {
            this.MolliePaymentsSubscriptionService[action]({
                id: this.subscription.id,
            }).then((response: any) => {
                if (response.success) {
                    this.loadDetails();
                    this.createNotificationSuccess({
                        message: this.$tc(successMessage),
                    });
                } else {
                    this.createNotificationError({ message: response.errors[0] });
                }
            });
        },
    },
};

Component.register('mollie-subscriptions-detail', componentConfig);
