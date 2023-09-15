import template from './sw-order-line-items-grid.html.twig';
import './sw-order-line-items-grid.scss';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';

// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;
// eslint-disable-next-line no-undef
const {string} = Shopware.Utils;

Component.override('sw-order-line-items-grid', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    inject: [
        'MolliePaymentsConfigService',
        'MolliePaymentsShippingService',
        'acl',
    ],
    props: {
        shopwareVersion: {
            type: Number,
            default: 6.4,
        },
    },

    /**
     *
     * @returns {{isLoading: boolean, shippingStatus: null: boolean, isShipOrderLoading: boolean, showShipItemModal: null, showShipOrderModal: boolean, showTrackingInfo: boolean, tracking: {carrier: string, code: string, url: string}, isShipItemLoading: boolean, shipQuantity: number}}
     */
    data() {
        return {
            isLoading: false,
            // ---------------------------------
            configShowRefundManager: true,
            showRefundModal: false,
            // ---------------------------------
            isShipOrderLoading: false,
            isShipItemLoading: false,
            shipQuantity: 0,
            showShipItemModal: null,
            shippingStatus: null,
            tracking: {
                carrier: '',
                code: '',
                url: '',
            },
            showShipOrderModal: false,
            showTrackingInfo: false,
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

        shippableLineItems() {
            return this.orderLineItems
                .filter((item) => this.shippableQuantity(item))
                .map((item) => {
                    return {
                        label: item.label,
                        quantity: this.shippableQuantity(item),
                    }
                });
        },

        isMollieOrder() {
            return (this.order.customFields !== null && 'mollie_payments' in this.order.customFields);
        },

        isShippingPossible() {

            if (!this.isMollieOrder) {
                return false;
            }

            const orderAttributes = new OrderAttributes(this.order);

            // this can happen on subscription renewals...they have no order id
            // and therefore the order cannot be shipped
            if (orderAttributes.getOrderId() === '') {
                return false;
            }

            return this.shippableLineItems.length > 0;
        },

        /**
         *
         * @returns {boolean}
         */
        isRefundManagerPossible() {

            if (!this.configShowRefundManager) {
                return;
            }

            return this.acl.can('mollie_refund_manager:read');
        },

        /**
         *
         * @returns {number}
         */
        possibleActionsCount() {
            let count = 0;

            if (this.isShippingPossible) {
                count += 1;
            }

            if (this.isRefundManagerPossible) {
                count += 1;
            }

            return count;
        },
    },

    created() {
        this.createdComponent();
    },
    watch: {
        showShipOrderModal(showShipOrderModal) {

            if (showShipOrderModal) {
                this.updateTrackingPrefilling();

            } else {
                this.isShipOrderLoading = false;
                this.resetTracking();
            }
        },
    },
    methods: {

        async createdComponent() {
            // Do not attempt to load the shipping status if this isn't a Mollie order,
            // or it will trigger an exception in the API.
            let refundManagerPossible = false;


            if (this.isMollieOrder) {
                await this.getShippingStatus();

                const me = this;

                this.MolliePaymentsConfigService.getRefundManagerConfig(this.order.salesChannelId).then((response) => {
                    me.configShowRefundManager = response.enabled;
                });
                refundManagerPossible = this.isRefundManagerPossible;


            }
            this.$emit('refund-manager-possible', refundManagerPossible)
            this.$emit('shipping-possible', this.isShippingPossible)
        },


        // ==============================================================================================//
        //  REFUND MANAGER

        onOpenRefundManager() {
            this.showRefundModal = true;
            this.$emit('toggle-refund-manager-modal', this.showRefundModal);
        },

        onCloseRefundManager() {
            this.showRefundModal = false;
            this.$emit('toggle-refund-manager-modal', this.showRefundModal);
        },

        //==== Shipping =============================================================================================//

        async getShippingStatus() {
            await this.MolliePaymentsShippingService
                .status({
                    orderId: this.order.id,
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
            if (this.showTrackingInfo && !this.validateTracking()) {
                this.createNotificationError({
                    message: this.$tc('mollie-payments.modals.shipping.tracking.invalid'),
                });
                return;
            }

            this.isShipOrderLoading = true;

            this.MolliePaymentsShippingService
                .shipOrder({
                    orderId: this.order.id,
                    trackingCarrier: this.tracking.carrier,
                    trackingCode: this.tracking.code,
                    trackingUrl: this.tracking.url,
                })
                .then(() => {
                    this.onCloseShipOrderModal();
                })
                .then(async () => {
                    this.$emit('ship-item-success');
                    await this.getShippingStatus();
                    this.$emit('shipping-possible', this.isShippingPossible)
                })
                .catch((response) => {
                    this.createNotificationError({
                        message: response.message,
                    });
                });
        },

        onOpenShipItemModal(item) {
            this.showShipItemModal = item.id;

            this.updateTrackingPrefilling();
        },

        onCloseShipItemModal() {
            this.isShipItemLoading = false;
            this.showShipItemModal = false;
            this.shipQuantity = 0;
            this.resetTracking();
        },

        onConfirmShipItem(item) {
            if (this.shipQuantity === 0) {
                this.createNotificationError({
                    message: this.$tc('mollie-payments.modals.shipping.item.noQuantity'),
                });
                return;
            }

            if (this.showTrackingInfo && !this.validateTracking()) {
                this.createNotificationError({
                    message: this.$tc('mollie-payments.modals.shipping.tracking.invalid'),
                });
                return;
            }

            this.isShipItemLoading = true;

            this.MolliePaymentsShippingService
                .shipItem({
                    orderId: this.order.id,
                    itemId: item.id,
                    quantity: this.shipQuantity,
                    trackingCarrier: this.tracking.carrier,
                    trackingCode: this.tracking.code,
                    trackingUrl: this.tracking.url,
                })
                .then(() => {
                    this.createNotificationSuccess({
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

            if (this.shippingStatus === null || this.shippingStatus === undefined) {
                return '~';
            }

            const itemShippingStatus = this.shippingStatus[item.id];

            if (itemShippingStatus === null || itemShippingStatus === undefined) {
                return '~';
            }
            return itemShippingStatus.quantityShippable;
        },

        shippedQuantity(item) {
            if (this.shippingStatus === null || this.shippingStatus === undefined) {
                return '~';
            }

            const itemShippingStatus = this.shippingStatus[item.id];

            if (itemShippingStatus === null || itemShippingStatus === undefined) {
                return '~';
            }

            return itemShippingStatus.quantityShipped;
        },

        //==== Tracking =============================================================================================//

        updateTrackingPrefilling() {
            // if we have at least 1 tracking code in the order
            // then try to prefill our tracking information
            // also automatically enable the tracking data (it can be turned off again by the merchant)
            if (this.order.deliveries.length) {
                const delivery = this.order.deliveries.first();
                this.showTrackingInfo = (delivery.trackingCodes.length >= 1);
            } else {
                this.showTrackingInfo = false;
            }
        },

        resetTracking() {
            this.showTrackingInfo = false;
            this.tracking = {
                carrier: '',
                code: '',
                url: '',
            };
        },

        validateTracking() {
            return !string.isEmptyOrSpaces(this.tracking.carrier)
                && !string.isEmptyOrSpaces(this.tracking.code)
        },
    },
});
