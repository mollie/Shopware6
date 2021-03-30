import template from './sw-order-line-items-grid.html.twig';

const {Component, Mixin} = Shopware;

Component.override('sw-order-line-items-grid', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [
        'MolliePaymentsRefundService',
        'MolliePaymentsShippingService',
    ],

    props: {
        refundableAmount: {
            type: Number,
            required: true
        },
        refundedAmount: {
            type: Number,
            required: true
        },
        refunds: {
            type: Array,
            required: true
        },
    },

    data() {
        return {
            isLoading: false,
            selectedItems: {},
            showRefundModal: false,
            showShippingModal: false,
            createCredit: false,
            quantityToShip: 1,
            refundAmount: 0.0,
            shippingQuantity: 0
        };
    },

    computed: {
        getLineItemColumns() {
            const columnDefinitions = this.$super('getLineItemColumns');

            columnDefinitions.push(
                {
                    property: 'customFields.shippedQuantity',
                    label: this.$tc('sw-order.detailExtended.columnShipped'),
                    allowResize: false,
                    align: 'right',
                    inlineEdit: false,
                    width: '100px'
                }
            );

            return columnDefinitions;
        },

        getRefundListColumns() {
            return [
                {
                    property: 'amount.value',
                    label: this.$tc('sw-order.detailExtended.columnShipped'),
                    width: '100px'
                },
                {
                    property: 'status',
                    label: this.$tc('sw-order.detailExtended.columnShipped'),
                    width: '100px'
                },

                {
                    property: 'createdAt',
                    label: this.$tc('sw-order.detailExtended.columnShipped'),
                    width: '100px'
                },
            ];
        },

        isMollieOrder() {
            return (this.order.customFields !== null && 'mollie_payments' in this.order.customFields);
        },

        isRefundable() {
            return this.refundableAmount > 0;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
        },

        onOpenRefundModal() {
            this.showRefundModal = true;
        },

        onCloseRefundModal() {
            this.showRefundModal = false;
        },

        onConfirmRefund() {
            this.MolliePaymentsRefundService
                .refund({
                    orderId: this.order.id,
                    amount: this.refundAmount,
                })
                .then((response) => {
                    if (response.success) {
                        this.createNotificationSuccess({
                            message: this.$tc('mollie-payments.modals.refund.success')
                        });
                        this.showRefundModal = false;
                    } else {
                        this.createNotificationError({
                            message: this.$tc('mollie-payments.modals.refund.error')
                        });
                    }
                })
                .then(() => {
                    this.$emit('refund-success');
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message
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
                    refundId: item.id
                })
                .then((response) => {
                    if (response.success) {
                        this.createNotificationSuccess({
                            message: this.$tc('mollie-payments.modals.refund.success')
                        });
                        this.showRefundModal = false;
                    } else {
                        this.createNotificationError({
                            message: this.$tc('mollie-payments.modals.refund.error')
                        });
                    }
                })
                .then(() => {
                    this.$emit('refund-cancelled');
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message
                    });
                });
        },

        onShipItem(item) {
            this.showShippingModal = item.id;
        },

        onCloseShippingModal() {
            this.showShippingModal = false;
        },

        onConfirmShipping(item) {
            this.showShippingModal = false;

            if (this.quantityToShip > 0) {
                this.MolliePaymentsShippingService.ship({
                    itemId: item.id,
                    versionId: item.versionId,
                    quantity: this.quantityToShip
                })
                    .then(document.location.reload());
            }

            this.quantityToShip = 0;
        },

        isShippable(item) {
            let shippable = false;

            if (
                item.type === 'product'
                && (
                    item.customFields !== undefined
                    && item.customFields !== null
                    && item.customFields.mollie_payments !== undefined
                    && item.customFields.mollie_payments !== null
                    && item.customFields.mollie_payments.order_line_id !== undefined
                    && item.customFields.mollie_payments.order_line_id !== null
                )
                && (
                    item.customFields.shippedQuantity === undefined
                    || parseInt(item.customFields.shippedQuantity, 10) < item.quantity
                )
            ) {
                shippable = true;
            }

            return shippable;
        },

        shippableQuantity(item) {
            if (
                item.customFields !== undefined
                && item.customFields.shippedQuantity !== undefined
                && item.customFields.refundedQuantity !== undefined
            ) {
                return item.quantity - parseInt(item.customFields.shippedQuantity, 10) - parseInt(item.customFields.refundedQuantity, 10);
            }

            if (
                item.customFields !== undefined
                && item.customFields.shippedQuantity === undefined
                && item.customFields.refundedQuantity !== undefined
            ) {
                return item.quantity - parseInt(item.customFields.refundedQuantity, 10);
            }

            return item.quantity;
        },
    }
});
