import template from './sw-order-line-items-grid.html.twig';
import './sw-order-line-items-grid.scss';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';
import RefundManager from '../../../../components/mollie-refund-manager/RefundManager';
import MollieShipping from '../../../../components/mollie-ship-order/MollieShipping';
import MollieShippingEvents from '../../../../components/mollie-ship-order/MollieShippingEvents';

// eslint-disable-next-line no-undef
const { Component, Mixin } = Shopware;
// eslint-disable-next-line no-undef
const { string } = Shopware.Utils;

Component.override('sw-order-line-items-grid', {
    template,

    mixins: [Mixin.getByName('notification')],

    inject: ['MolliePaymentsConfigService', 'MolliePaymentsShippingService', 'MolliePaymentsItemCancelService', 'acl'],
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
            isRefundManagerPossible: false,
            // ---------------------------------
            isShippingPossible: false,
            showShipOrderModal: false,
            isShipOrderLoading: false,
            isShipItemLoading: false,
            shipQuantity: 0,
            showShipItemModal: null,
            cancelItemModal: null,
            shippingStatus: null,
            cancelStatus: null,
            tracking: {
                carrier: '',
                code: '',
                url: '',
            },
            showTrackingInfo: false,
            refundedManagerService: null,
            shippingManagerService: null,
            EVENT_TOGGLE_REFUND_MANAGER: 'toggle-refund-manager-modal',
        };
    },

    computed: {
        /**
         *
         * @returns {*}
         */
        getLineItemColumns() {
            const columnDefinitions = this.$super('getLineItemColumns');

            columnDefinitions.push({
                property: 'shippedQuantity',
                label: this.$tc('sw-order.detailExtended.columnShipped'),
                allowResize: false,
                align: 'right',
                inlineEdit: false,
                width: '100px',
            });

            columnDefinitions.push({
                property: 'canceledQuantity',
                label: this.$tc('sw-order.detailExtended.columnCanceled'),
                allowResize: false,
                align: 'right',
                inlineEdit: false,
                width: '100px',
            });
            return columnDefinitions;
        },

        /**
         *
         * @returns {boolean}
         */
        isMollieOrder() {
            const attr = new OrderAttributes(this.order);
            return attr.isMollieOrder();
        },

        mollieId() {
            const attr = new OrderAttributes(this.order);

            return attr.getMollieID();
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

    methods: {
        /**
         *
         * @returns {Promise<void>}
         */
        createdComponent() {
            if (!this.isMollieOrder) {
                return;
            }

            // hook into our shipping events
            // we close our modals if shipping happened
            if (this.$root && this.$root.$on) {
                this.$root.$on(MollieShippingEvents.EventShippedOrder, () => {
                    this.onCloseShipOrderModal();
                });
            } else {
                Shopware.Utils.EventBus.on(MollieShippingEvents.EventShippedOrder, () => {
                    this.onCloseShipOrderModal();
                });
            }

            this.refundedManagerService = new RefundManager(this.MolliePaymentsConfigService, this.acl);
            this.shippingManagerService = new MollieShipping(this.MolliePaymentsShippingService);

            this.reloadData();
        },

        /**
         *
         */
        async reloadData() {
            this.isShippingPossible = (await this.shippingManagerService?.isShippingPossible(this.order)) || false;
            this.isRefundManagerPossible =
                (await this.refundedManagerService?.isRefundManagerAvailable(
                    this.order.salesChannelId,
                    this.order.id,
                )) || false;

            await this.loadMollieShippingStatus();
            await this.loadMollieCancelStatus();
        },

        // ==============================================================================================//
        //  REFUND MANAGER

        /**
         *
         */
        onOpenRefundManager() {
            this.showRefundModal = true;
        },

        /**
         *
         */
        onCloseRefundManager() {
            this.showRefundModal = false;
            location.reload();
        },

        //==== Shipping =============================================================================================//

        /**
         *
         */
        onOpenShipOrderModal() {
            this.showShipOrderModal = true;
        },

        /**
         *
         */
        onCloseShipOrderModal() {
            this.showShipOrderModal = false;
            this.reloadData();
        },

        //==== Shipping Line Item =============================================================================================//
        // unfortunately too tightly integrated in here

        /**
         *
         * @param item
         */
        onOpenShipItemModal(item) {
            this.showShipItemModal = item.id;
            this.updateTrackingPrefilling();
        },

        onOpenCancelItemModal(item) {
            this.cancelItemModal = item.id;
        },
        closeCancelItemModal() {
            this.cancelItemModal = null;
        },

        /**
         *
         */
        onCloseShipItemModal() {
            this.isShipItemLoading = false;
            this.showShipItemModal = false;
            this.shipQuantity = 0;
            this.resetTracking();

            this.reloadData();
        },

        /**
         *
         * @returns {Promise<void>}
         */
        async loadMollieShippingStatus() {
            await this.MolliePaymentsShippingService.status({
                orderId: this.order.id,
            }).then((response) => {
                this.shippingStatus = response;
            });
        },

        async loadMollieCancelStatus() {
            await this.MolliePaymentsItemCancelService.status({ mollieOrderId: this.mollieId }).then((response) => {
                this.cancelStatus = response;
            });
        },
        /**
         *
         * @param item
         */
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
            if(this.isShipItemLoading === true){
                return;
            }
            this.isShipItemLoading = true;

            this.MolliePaymentsShippingService.shipItem({
                orderId: this.order.id,
                itemId: item.id,
                quantity: this.shipQuantity,
                trackingCarrier: this.tracking.carrier,
                trackingCode: this.tracking.code,
                trackingUrl: this.tracking.url,
            })
                .then(() => {
                    this.isShipItemLoading = false;
                    this.createNotificationSuccess({
                        message: this.$tc('mollie-payments.modals.shipping.item.success'),
                    });
                    this.onCloseShipItemModal();
                    // send global event
                    this.$root.$emit(MollieShippingEvents.EventShippedOrder);
                })
                .then(() => {
                    this.$emit('ship-item-success');
                })
                .catch((response) => {
                    this.isShipItemLoading = false;
                    this.createNotificationError({
                        message: response.response.data.message,
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
        canceledQuantity(item) {
            if (this.cancelStatus === undefined || this.cancelStatus === null) {
                return '~';
            }
            const itemStatus = this.cancelStatus[item.id];
            if (itemStatus === undefined || itemStatus === null) {
                return '~';
            }
            return itemStatus.quantityCanceled;
        },
        isCancelable(item) {
            if (this.cancelStatus === undefined || this.cancelStatus === null) {
                return false;
            }

            const itemStatus = this.cancelStatus[item.id];
            if (itemStatus === undefined || itemStatus === null) {
                return false;
            }

            return itemStatus.isCancelable;
        },

        getCancelData(item) {
            if (this.cancelStatus === undefined || this.cancelStatus === null) {
                return {};
            }
            const itemStatus = this.cancelStatus[item.id];
            if (itemStatus === undefined) {
                return {};
            }
            itemStatus.shopwareItemId = item.id;
            itemStatus.label = item.label;
            itemStatus.payload = item.payload;
            return itemStatus;
        },
        //==== Tracking =============================================================================================//

        updateTrackingPrefilling() {
            // if we have at least 1 tracking code in the order
            // then try to prefill our tracking information
            // also automatically enable the tracking data (it can be turned off again by the merchant)
            if (this.order.deliveries.length) {
                const delivery = this.order.deliveries.first();
                this.showTrackingInfo = delivery.trackingCodes.length >= 1;
            } else {
                this.showTrackingInfo = false;
            }
        },

        validateTracking() {
            return !string.isEmptyOrSpaces(this.tracking.carrier) && !string.isEmptyOrSpaces(this.tracking.code);
        },

        resetTracking() {
            this.showTrackingInfo = false;
            this.tracking = {
                carrier: '',
                code: '',
                url: '',
            };
        },
    },
});
