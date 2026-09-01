import template from './mollie-order-tab.html.twig';
import './mollie-order-tab.scss';
import MollieShippingEvents from '../../../../components/mollie-ship-order/MollieShippingEvents';
import getLatestTransaction from '../../getLatestTransaction';
import { getStore } from '../../../../../../core/service/utils/store.utils';

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
    molliePaymentUrlCopied: boolean;
    initialShippingStatus: any;
    initialCancelStatus: any;

    [key: string]: any;
}

const componentConfig: ThisType<MollieOrderTab> = {
    template,

    mixins: [Mixin.getByName('notification')],

    inject: ['MollieOrderDetailsService', 'MolliePaymentsRefundService', 'acl'],

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
            molliePaymentUrlCopied: false,
            initialShippingStatus: null,
            initialCancelStatus: null,
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

        copyPaymentUrlToClipboard() {
            const fallback = async (url: string) => {
                await navigator.clipboard.writeText(url);
            };

            const clipboard =
                typeof Shopware.Utils.dom.copyToClipboard === 'function'
                    ? Shopware.Utils.dom.copyToClipboard
                    : fallback;

            clipboard(this.molliePaymentUrl);
            this.molliePaymentUrlCopied = true;
        },

        onMolliePaymentUrlProcessFinished(value: boolean) {
            this.molliePaymentUrlCopied = value;
        },
    },
};

Component.register('mollie-order-tab', componentConfig);
