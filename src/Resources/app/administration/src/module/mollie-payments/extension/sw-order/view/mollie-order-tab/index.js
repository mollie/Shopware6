import template from './mollie-order-tab.html.twig';
import './mollie-order-tab.scss';
import MollieShippingEvents from '../../../../components/mollie-ship-order/MollieShippingEvents';

// eslint-disable-next-line no-undef
const { Component, Mixin, Filter } = Shopware;

Component.register('mollie-order-tab', {
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
            // eslint-disable-next-line no-undef
            return Shopware.State.get('swOrderDetail')?.order ?? null;
        },

        context() {
            // eslint-disable-next-line no-undef
            return Shopware.State.get('swOrderDetail')?.versionContext ?? null;
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
            if (!this.order?.deliveries) return [];
            return Array.from(this.order.deliveries).slice(1);
        },

        taxStatus() {
            return this.order?.price?.taxStatus ?? 'gross';
        },

        sortedCalculatedTaxes() {
            if (!this.order?.price?.calculatedTaxes) return [];
            const raw = this.order.price.calculatedTaxes;
            const taxes = Array.isArray(raw) ? raw : Object.values(raw);
            return taxes
                .filter(function (t) {
                    return t.tax !== 0;
                })
                .sort(function (a, b) {
                    return b.taxRate - a.taxRate;
                });
        },

        displayRounded() {
            if (!this.order) return false;
            return (
                this.order.totalRounding.interval !== 0.01 ||
                this.order.totalRounding.decimals !== this.order.itemRounding.decimals
            );
        },

        orderTotal() {
            if (!this.order) return 0;
            return this.displayRounded ? this.order.price.rawTotal : this.order.price.totalPrice;
        },
    },

    watch: {
        order: function () {
            this.loadData();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$root && this.$root.$on) {
                this.$root.$on(MollieShippingEvents.EventShippedOrder, () => {
                    this.onCloseShippingManager();
                    this.loadData();
                });
            } else {
                // eslint-disable-next-line no-undef
                Shopware.Utils.EventBus.on(MollieShippingEvents.EventShippedOrder, () => {
                    this.onCloseShippingManager();
                    this.loadData();
                });
            }

            this.loadData();
        },

        loadData() {
            if (!this.order || !this.order.id) {
                return;
            }

            this.isDetailsLoading = true;

            this.MollieOrderDetailsService.getDetails(this.order.id)
                .then((response) => {
                    this.details = response;

                    if (!response.isMollieOrder) {
                        return;
                    }

                    const refundManager = response.refundManager ?? {};
                    const isAuthorized =
                        this.order?.transactions?.[0]?.stateMachineState?.technicalName === 'authorized';
                    const aclAllowed = this.acl.can('mollie_refund_manager:read');
                    this.isRefundManagerPossible = !isAuthorized && aclAllowed && (refundManager.enabled ?? false);

                    const shippingStatus = response.shipping?.status ?? {};
                    this.isShippingPossible = Object.values(shippingStatus).some(function (s) {
                        return (s.quantityShippable ?? 0) > 0;
                    });

                    const shippingTotal = response.shipping?.total ?? {};
                    this.shippedAmount = Math.round((shippingTotal.amount ?? 0) * 100) / 100;
                    this.shippedQuantity = shippingTotal.quantity ?? 0;

                    this.initialShippingStatus = shippingStatus;
                    this.initialCancelStatus = response.cancelItem ?? {};
                })
                .catch(
                    function (response) {
                        this.createNotificationError({ message: response.message });
                    }.bind(this),
                )
                .finally(
                    function () {
                        this.isDetailsLoading = false;
                    }.bind(this),
                );
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
            const fallback = async function (e) {
                await navigator.clipboard.writeText(e);
            };

            // eslint-disable-next-line no-undef
            const clipboard =
                typeof Shopware.Utils.dom.copyToClipboard === 'function'
                    ? Shopware.Utils.dom.copyToClipboard
                    : fallback;

            clipboard(this.molliePaymentUrl);
            this.molliePaymentUrlCopied = true;
        },

        onMolliePaymentUrlProcessFinished(value) {
            this.molliePaymentUrlCopied = value;
        },
    },
});
