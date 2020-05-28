import template from './sw-order-line-items-grid.html.twig';

const { Component, Service } = Shopware;

Component.override('sw-order-line-items-grid', {
    template,

    inject: [
        'MolliePaymentsRefundService',
        'MolliePaymentsShippingService',
    ],

    data() {
        return {
            isLoading: false,
            selectedItems: {},
            showRefundModal: false,
            showShippingModal: false,
            createCredit: false,
            quantityToRefund: 1,
            quantityToShip: 1,
            refundQuantity: 0,
            shippingQuantity: 0
        };
    },

    computed: {
        getLineItemColumns() {
            const columnDefinitions = this.$super('getLineItemColumns');

            columnDefinitions.push(
                {
                    property: 'customFields.refundedQuantity',
                    label: this.$tc('sw-order.detailExtended.columnRefunded'),
                    allowResize: false,
                    align: 'right',
                    inlineEdit: false,
                    width: '100px'
                }
            );

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
        }
    },

    methods: {
        onRefundItem(item) {
            this.showRefundModal = item.id;
        },

        onCloseRefundModal() {
            this.showRefundModal = false;
        },

        onConfirmRefund(item) {
            this.showRefundModal = false;

            if (this.quantityToRefund > 0) {
                this.MolliePaymentsRefundService.refund({
                    itemId: item.id,
                    versionId: item.versionId,
                    quantity: this.quantityToRefund,
                    createCredit: this.createCredit
                })
                    .then(document.location.reload());
            }

            this.quantityToRefund = 0;
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

        isRefundable(item) {
            let refundable = false;

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
                    item.customFields.refundedQuantity === undefined
                    || parseInt(item.customFields.refundedQuantity, 10) < item.quantity
                )
            ) {
                refundable = true;
            }

            return refundable;
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

        refundableQuantity(item) {
            if (
                item.customFields !== undefined
                && item.customFields.refundedQuantity !== undefined
            ) {
                return item.quantity - parseInt(item.customFields.refundedQuantity, 10);
            }

            return item.quantity;
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