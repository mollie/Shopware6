import template from './mollie-order-tab.html.twig';
import './mollie-order-tab.scss';
import MollieShippingEvents from '../../../../components/mollie-ship-order/MollieShippingEvents';
import getLatestTransaction from '../../getLatestTransaction';
import { getStore } from '../../../../../../core/service/utils/store.utils';
import VersionCompare from '../../../../../../core/service/utils/version-compare.utils';

const { Component, Mixin, Filter } = Shopware;

interface MollieOrderTab {
    details: any;
    isDetailsLoading: boolean;
    isRefundManagerPossible: boolean;
    isShippingPossible: boolean;
    showRefundModal: boolean;
    showShippingModal: boolean;
    shippedAmount: number;
    shippedQuantity: number;
    isFetchingMollieData: boolean;
    mollieDataFetched: boolean;
    initialShippingStatus: any;
    initialCancelStatus: any;
    editMolliePaymentId: string;
    editMollieOrderId: string;
    showEditMollieIdsConfirm: boolean;
    isSavingMollieIds: boolean;
    mollieIdsSaved: boolean;

    [key: string]: any;
}

const componentConfig: ThisType<MollieOrderTab> = {
    template,

    mixins: [Mixin.getByName('notification')],

    inject: {
        MollieOrderDetailsService: { from: 'MollieOrderDetailsService' },
        MolliePaymentsRefundService: { from: 'MolliePaymentsRefundService' },
        repositoryFactory: { from: 'repositoryFactory' },
        acl: { from: 'acl' },
        // Both come from sw-order-detail and are used the same way the payment status dropdown uses
        // them: ask about unsaved edits before the action, reload the order after it. Older Shopware
        // versions do not provide them, hence the defaults.
        swOrderDetailAskAndSaveEdits: { from: 'swOrderDetailAskAndSaveEdits', default: () => true },
        swOrderDetailOnSaveEdits: { from: 'swOrderDetailOnSaveEdits', default: null },
    },

    props: {
        orderId: {
            type: String,
            required: true,
        },
        isSaveSuccessful: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            details: null,
            isDetailsLoading: false,
            isRefundManagerPossible: false,
            isShippingPossible: false,
            showRefundModal: false,
            showShippingModal: false,
            shippedAmount: 0,
            shippedQuantity: 0,
            isFetchingMollieData: false,
            mollieDataFetched: false,
            initialShippingStatus: null,
            initialCancelStatus: null,
            editMolliePaymentId: '',
            editMollieOrderId: '',
            showEditMollieIdsConfirm: false,
            isSavingMollieIds: false,
            mollieIdsSaved: false,
        };
    },

    computed: {
        order() {
            return this.getSwOrderDetail()?.order ?? null;
        },

        context() {
            return this.getSwOrderDetail()?.versionContext ?? null;
        },

        isMollieOrder() {
            return this.details?.isMollieOrder ?? false;
        },

        mollieId() {
            return this.details?.mollieId ?? null;
        },

        thirdPartyPaymentId() {
            return this.details?.thirdPartyPaymentId ?? null;
        },

        hasCreditCardData() {
            return this.details?.creditCard != null;
        },

        creditCardLabel() {
            return this.details?.creditCard?.label ?? '';
        },

        creditCardNumber() {
            return '**** **** **** ' + (this.details?.creditCard?.number ?? '');
        },

        creditCardHolder() {
            return this.details?.creditCard?.holder ?? '';
        },

        hasBankTransferData() {
            return this.details?.bankTransfer != null;
        },

        bankTransferReference() {
            return this.details?.bankTransfer?.transferReference ?? '';
        },

        hasPaymentLink() {
            return !!this.details?.checkoutUrl;
        },

        molliePaymentUrl() {
            return this.details?.checkoutUrl ?? '';
        },

        latestTransactionId() {
            return getLatestTransaction(this.order?.transactions)?.id ?? null;
        },

        canFetchMollieData() {
            return this.isMollieOrder && this.latestTransactionId !== null;
        },

        editWarningVariant() {
            return new VersionCompare().greaterOrEqual(Shopware.Context.app.config.version, '6.7.0.0')
                ? 'critical'
                : 'error';
        },

        canSaveMollieIds() {
            return (
                this.latestTransactionId !== null &&
                this.editMolliePaymentId.trim() !== '' &&
                this.acl.can('order.editor')
            );
        },

        isSubscription() {
            return this.details?.isSubscription ?? false;
        },

        subscriptionId() {
            return this.details?.subscriptionId ?? '';
        },

        currencyFilter() {
            return Filter.getByName('currency');
        },

        currency() {
            return this.order?.currency ?? null;
        },

        delivery() {
            return this.order?.deliveries?.[0] ?? null;
        },

        deliveryDiscounts() {
            if (!this.order?.deliveries) {
                return [];
            }

            return Array.from(this.order.deliveries).slice(1);
        },

        taxStatus() {
            return this.order?.price?.taxStatus ?? 'gross';
        },

        sortedCalculatedTaxes() {
            if (!this.order?.price?.calculatedTaxes) {
                return [];
            }

            const raw = this.order.price.calculatedTaxes;
            const taxes = Array.isArray(raw) ? raw : Object.values(raw);

            return taxes.filter((tax: any) => tax.tax !== 0).sort((a: any, b: any) => b.taxRate - a.taxRate);
        },

        displayRounded() {
            if (!this.order) {
                return false;
            }

            return (
                this.order.totalRounding.interval !== 0.01 ||
                this.order.totalRounding.decimals !== this.order.itemRounding.decimals
            );
        },

        orderTotal() {
            if (!this.order) {
                return 0;
            }

            return this.displayRounded ? this.order.price.rawTotal : this.order.price.totalPrice;
        },
    },

    watch: {
        orderId: {
            handler() {
                this.loadData();
            },
            immediate: true,
        },
        order() {
            this.loadData();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        getSwOrderDetail() {
            return getStore('swOrderDetail');
        },

        createdComponent() {
            const onShipped = () => {
                this.onCloseShippingManager();
                this.loadData();
            };

            if (this.$root && this.$root.$on) {
                this.$root.$on(MollieShippingEvents.EventShippedOrder, onShipped);
            } else {
                Shopware.Utils.EventBus.on(MollieShippingEvents.EventShippedOrder, onShipped);
            }
        },

        loadData() {
            if (!this.orderId) {
                return;
            }

            this.isDetailsLoading = true;

            this.MollieOrderDetailsService.getDetails(this.orderId)
                .then((response: any) => {
                    this.details = response;

                    if (!response.isMollieOrder) {
                        return;
                    }

                    const refundManager = response.refundManager ?? {};
                    const latestTransaction = getLatestTransaction(this.order?.transactions);
                    const mollieCustomFields = latestTransaction?.customFields?.mollie_payments ?? {};
                    this.editMolliePaymentId = mollieCustomFields.id ?? '';
                    this.editMollieOrderId = mollieCustomFields.orderId ?? '';
                    const isAuthorized = latestTransaction?.stateMachineState?.technicalName === 'authorized';
                    const aclAllowed = this.acl.can('mollie_refund_manager:read');
                    this.isRefundManagerPossible = !isAuthorized && aclAllowed && (refundManager.enabled ?? false);

                    const shippingStatus = response.shipping?.status ?? {};
                    this.isShippingPossible = Object.values(shippingStatus).some(
                        (status: any) => (status.shippableQuantity ?? 0) > 0,
                    );

                    const shippingTotal = response.shipping?.total ?? {};
                    this.shippedAmount = Math.round((shippingTotal.amount ?? 0) * 100) / 100;
                    this.shippedQuantity = shippingTotal.quantity ?? 0;

                    this.initialShippingStatus = shippingStatus;
                    this.initialCancelStatus = response.cancelItem ?? {};
                })
                .catch((response: any) => {
                    this.createNotificationError({ message: response.message });
                })
                .finally(() => {
                    this.isDetailsLoading = false;
                });
        },

        onOpenRefundManager() {
            this.showRefundModal = true;
        },

        onCloseRefundManager() {
            this.showRefundModal = false;
            this.loadData();
        },

        onOpenShippingManager() {
            this.showShippingModal = true;
        },

        onCloseShippingManager() {
            this.showShippingModal = false;
        },

        async onFetchMollieData() {
            const transactionId = this.latestTransactionId;
            if (transactionId === null) {
                return;
            }

            const proceed = await this.swOrderDetailAskAndSaveEdits();
            if (!proceed) {
                return;
            }

            this.isFetchingMollieData = true;

            try {
                await this.MollieOrderDetailsService.triggerWebhook(transactionId);
                // The detail page works on a draft version of the order that was branched off when
                // the page was opened, so a plain reload still returns the payment status from
                // before the webhook ran. Merging that draft and branching a new one off the live
                // order is what the payment status dropdown does after a transition, and it is what
                // makes the fetched status appear.
                await this.swOrderDetailOnSaveEdits?.();
                this.loadData();
                this.mollieDataFetched = true;
            } catch (error: any) {
                this.createNotificationError({ message: error.message });
            } finally {
                this.isFetchingMollieData = false;
            }
        },

        onFetchMollieDataProcessFinished(value: boolean) {
            this.mollieDataFetched = value;
        },

        onSaveMollieIds() {
            this.showEditMollieIdsConfirm = true;
        },

        onCloseEditMollieIdsConfirm() {
            this.showEditMollieIdsConfirm = false;
        },

        async onConfirmSaveMollieIds() {
            this.showEditMollieIdsConfirm = false;

            const transactionId = this.latestTransactionId;
            if (transactionId === null) {
                return;
            }

            const proceed = await this.swOrderDetailAskAndSaveEdits();
            if (!proceed) {
                return;
            }

            this.isSavingMollieIds = true;

            const paymentId = this.editMolliePaymentId.trim();
            const mollieOrderId = this.editMollieOrderId.trim();
            const mollieIds = {
                id: paymentId,
                payment_id: paymentId,
                orderId: mollieOrderId === '' ? null : mollieOrderId,
                order_id: mollieOrderId,
            };

            const transactionRepository = this.repositoryFactory.create('order_transaction');
            const orderRepository = this.repositoryFactory.create('order');
            const context = Shopware.Context.api;

            try {
                const [transaction, order] = await Promise.all([
                    transactionRepository.get(transactionId, context),
                    orderRepository.get(this.orderId, context),
                ]);

                [transaction, order].forEach((entity: any) => {
                    const customFields = entity.customFields ?? {};
                    entity.customFields = {
                        ...customFields,
                        mollie_payments: { ...(customFields.mollie_payments ?? {}), ...mollieIds },
                    };
                });

                await transactionRepository.save(transaction, context);
                await orderRepository.save(order, context);
                await this.swOrderDetailOnSaveEdits?.();
                this.loadData();
                this.mollieIdsSaved = true;
            } catch (error: any) {
                this.createNotificationError({ message: error.message });
            } finally {
                this.isSavingMollieIds = false;
            }
        },

        onSaveMollieIdsProcessFinished(value: boolean) {
            this.mollieIdsSaved = value;
        },
    },
};

Component.register('mollie-order-tab', componentConfig);
