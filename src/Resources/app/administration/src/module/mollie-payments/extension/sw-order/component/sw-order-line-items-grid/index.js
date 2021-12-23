import template from './sw-order-line-items-grid.html.twig';

// eslint-disable-next-line no-undef
const { Component, Mixin } = Shopware;

Component.override('sw-order-line-items-grid', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    inject: [
        'MolliePaymentsRefundService',
        'MolliePaymentsShippingService',
    ],

    props: {
        remainingAmount: {
            type: Number,
            required: true,
        },
        refundedAmount: {
            type: Number,
            required: true,
        },
        voucherAmount: {
            type: Number,
            required: true,
        },
        refunds: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            refundAmount: 0.0,
            shipQuantity: 0,
            showRefundModal: false,
            showShipOrderModal: false,
            showShipItemModal: null,
            shippingStatus: null,
        };
    },

    computed: {
        getLineItemColumns() {
            const columnDefinitions = this.$super('getLineItemColumns');

            columnDefinitions.push(
                {
                    property: 'shippedQuantity',
                    label: this.$tc('sw-order.detailExtended.columnShipped'),
                    allowResize: false,
                    align: 'right',
                    inlineEdit: false,
                    width: '100px',
                }
            );

            return columnDefinitions;
        },

        getRefundListColumns() {
            return [
                {
                    property: 'amount.value',
                    label: this.$tc('mollie-payments.modals.refund.list.column.amount'),
                },
                {
                    property: 'status',
                    label: this.$tc('mollie-payments.modals.refund.list.column.status'),
                },
                {
                    property: 'createdAt',
                    label: this.$tc('mollie-payments.modals.refund.list.column.date'),
                    width: '100px',
                },
            ];
        },

        isMollieOrder() {
            return (this.order.customFields !== null && 'mollie_payments' in this.order.customFields);
        },

        canOpenRefundModal() {
            return this.remainingAmount > 0 || (this.refunds !== undefined && this.refunds.length > 0);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getShippingStatus();
        },

        //==== Refunds ==============================================================================================//

        onOpenRefundModal() {
            this.showRefundModal = true;
        },

        onCloseRefundModal() {
            this.showRefundModal = false;
        },

        onConfirmRefund() {
            if (this.refundAmount === 0.0) {
                this.createNotificationWarning({
                    message: this.$tc('mollie-payments.modals.refund.warning.low-amount'),
                });

                return;
            }

            this.MolliePaymentsRefundService
                .refund({
                    orderId: this.order.id,
                    amount: this.refundAmount,
                })
                .then((response) => {
                    if (response.success) {
                        this.createNotificationSuccess({
                            message: this.$tc('mollie-payments.modals.refund.success'),
                        });
                        this.showRefundModal = false;
                    } else {
                        this.createNotificationError({
                            message: this.$tc('mollie-payments.modals.refund.error'),
                        });
                    }
                })
                .then(() => {
                    this.$emit('refund-success');
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message,
                    });
                });
        },

        isRefundCancelable(item) {
            return item.isPending || item.isQueued;
        },

        cancelRefund(item) {
            this.MolliePaymentsRefundService
                .cancel({
                    orderId: this.order.id,
                    refundId: item.id,
                })
                .then((response) => {
                    if (response.success) {
                        this.createNotificationSuccess({
                            message: this.$tc('mollie-payments.modals.refund.success'),
                        });
                        this.showRefundModal = false;
                    } else {
                        this.createNotificationError({
                            message: this.$tc('mollie-payments.modals.refund.error'),
                        });
                    }
                })
                .then(() => {
                    this.$emit('refund-cancelled');
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message,
                    });
                });
        },

        getStatus(status) {
            return this.$tc('mollie-payments.modals.refund.list.status.' + status);
        },

        getStatusDescription(status) {
            return this.$tc('mollie-payments.modals.refund.list.status-description.' + status);
        },

        //==== Shipping =============================================================================================//

        async getShippingStatus() {
            await this.MolliePaymentsShippingService
                .status({
                    orderId: this.order.id
                })
                .then((response) => {
                    this.shippingStatus = response;
                });
        },

        onOpenShipOrderModal() {
            this.showShipOrderModal = true;
        },

        onCloseShipOrderModal() {
            this.showShipOrderModal = false;
        },

        onConfirmShipOrder() {
            this.MolliePaymentsShippingService
                .ship({
                    orderId: this.order.id
                })
                .then(() => {
                    this.onCloseShipOrderModal();
                })
                .then(() => {
                    this.$emit('ship-item-success');
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message,
                    });
                });
        },

        onOpenShipItemModal(item) {
            this.showShipItemModal = item.id;
        },

        onCloseShipItemModal() {
            this.showShipItemModal = false;
            this.shipQuantity = 0;
        },

        onConfirmShipItem(item) {
            if (this.shipQuantity === 0) {
                this.createNotificationError({
                    message: this.$tc('mollie-payments.modals.shipping.item.noQuantity'),
                });
                return;
            }

            this.MolliePaymentsShippingService
                .shipItem({
                    orderId: this.order.id,
                    itemId: item.id,
                    quantity: this.shipQuantity,
                })
                .then((response) => {
                    this.createNotificationError({
                        message: this.$tc('mollie-payments.modals.shipping.item.success'),
                    });
                    this.onCloseShipItemModal();
                })
                .then(() => {
                    this.$emit('ship-item-success');
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message,
                    });
                });
        },

        setMaxQuantity(item) {
            this.shipQuantity = this.shippableQuantity(item);
        },

        isShippable(item) {
            return this.shippableQuantity(item) > 0;
        },

        shippableQuantity(item) {
            if (this.shippingStatus === null) {
                return '~';
            }

            return this.shippingStatus[item.id].quantityShippable;
        },

        shippedQuantity(item) {
            if (this.shippingStatus === null) {
                return '~';
            }

            return this.shippingStatus[item.id].quantityShipped;
        },
    }
});
