import template from './mollie-order-line-items-grid.html.twig';
import OrderAttributes from '../../../../core/models/OrderAttributes';
import MollieShippingEvents from '../mollie-ship-order/MollieShippingEvents';

// eslint-disable-next-line no-undef
const { Component, Mixin } = Shopware;
// eslint-disable-next-line no-undef
const { string } = Shopware.Utils;

Component.extend('mollie-order-line-items-grid', 'sw-order-line-items-grid', {
    mixins: [Mixin.getByName('notification')],

    inject: ['MolliePaymentsShippingService', 'MolliePaymentsItemCancelService'],

    props: {
        initialShippingStatus: {
            type: Object,
            required: false,
            default: null,
        },
        initialCancelStatus: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
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
        };
    },

    computed: {
        getLineItemColumns() {
            const columns = this.$super('getLineItemColumns');

            columns.push({
                property: 'shippedQuantity',
                label: this.$tc('sw-order.detailExtended.columnShipped'),
                allowResize: false,
                align: 'right',
                inlineEdit: false,
                width: '100px',
            });

            columns.push({
                property: 'canceledQuantity',
                label: this.$tc('sw-order.detailExtended.columnCanceled'),
                allowResize: false,
                align: 'right',
                inlineEdit: false,
                width: '100px',
            });

            return columns;
        },

        isMollieOrder() {
            const attr = new OrderAttributes(this.order);
            return attr.isMollieOrder();
        },

        mollieId() {
            const attr = new OrderAttributes(this.order);
            return attr.getMollieID();
        },
    },

    watch: {
        initialCancelStatus(value) {
            if (value !== null && value !== undefined) {
                this.cancelStatus = value;
            }
        },
    },

    created() {
        if (!this.isMollieOrder) {
            return;
        }

        if (this.$root && this.$root.$on) {
            this.$root.$on(MollieShippingEvents.EventShippedOrder, () => {
                this.onCloseShipItemModal();
            });
        } else {
            // eslint-disable-next-line no-undef
            Shopware.Utils.EventBus.on(MollieShippingEvents.EventShippedOrder, () => {
                this.onCloseShipItemModal();
            });
        }

        if (this.initialShippingStatus !== null) {
            this.shippingStatus = this.initialShippingStatus;
        }

        if (this.initialCancelStatus !== null) {
            this.cancelStatus = this.initialCancelStatus;
        }

        if (this.initialShippingStatus === null || this.initialCancelStatus === null) {
            this.reloadData();
        }
    },

    methods: {
        async reloadData() {
            await this.loadMollieShippingStatus();
        },

        async loadMollieShippingStatus() {
            await this.MolliePaymentsShippingService.status({ orderId: this.order.id }).then(
                function (response) {
                    this.shippingStatus = response;
                }.bind(this),
            );
        },

        loadMollieCancelStatus(cancelResponse) {
            if (!cancelResponse || !cancelResponse.success || !cancelResponse.data) {
                return;
            }
            const cancelledMollieId = cancelResponse.data.id;
            const cancelledQuantity = cancelResponse.data.quantity || 0;

            const updated = {};
            Object.entries(this.cancelStatus || {}).forEach(function (pair) {
                const swItemId = pair[0];
                const status = pair[1];
                if (status.mollieId === cancelledMollieId) {
                    const newCancelableQty = Math.max(0, (status.cancelableQuantity || 0) - cancelledQuantity);
                    updated[swItemId] = Object.assign({}, status, {
                        quantityCanceled: (status.quantityCanceled || 0) + cancelledQuantity,
                        cancelableQuantity: newCancelableQty,
                        isCancelable: newCancelableQty > 0,
                    });
                } else {
                    updated[swItemId] = status;
                }
            });
            this.cancelStatus = updated;
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
            this.reloadData();
        },

        onOpenCancelItemModal(item) {
            this.cancelItemModal = item.id;
        },

        closeCancelItemModal() {
            this.cancelItemModal = null;
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

            if (this.isShipItemLoading === true) {
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
                .then(
                    function () {
                        this.isShipItemLoading = false;
                        this.createNotificationSuccess({
                            message: this.$tc('mollie-payments.modals.shipping.item.success'),
                        });
                        this.onCloseShipItemModal();

                        if (this.$root && this.$root.$emit) {
                            this.$root.$emit(MollieShippingEvents.EventShippedOrder);
                        } else {
                            // eslint-disable-next-line no-undef
                            Shopware.Utils.EventBus.emit(MollieShippingEvents.EventShippedOrder);
                        }
                    }.bind(this),
                )
                .then(
                    function () {
                        this.$emit('ship-item-success');
                    }.bind(this),
                )
                .catch(
                    function (response) {
                        this.isShipItemLoading = false;
                        this.createNotificationError({
                            message: response.response.data.message,
                        });
                    }.bind(this),
                );
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
            const status = this.shippingStatus[item.id];
            if (status === null || status === undefined) {
                return '~';
            }
            return status.quantityShippable;
        },

        shippedQuantity(item) {
            if (this.shippingStatus === null || this.shippingStatus === undefined) {
                return '~';
            }
            const status = this.shippingStatus[item.id];
            if (status === null || status === undefined) {
                return '~';
            }
            return status.quantityShipped;
        },

        canceledQuantity(item) {
            if (this.cancelStatus === undefined || this.cancelStatus === null) {
                return '~';
            }
            const status = this.cancelStatus[item.id];
            if (status === undefined || status === null) {
                return '~';
            }
            return status.quantityCanceled;
        },

        isCancelable(item) {
            if (this.cancelStatus === undefined || this.cancelStatus === null) {
                return false;
            }
            const status = this.cancelStatus[item.id];
            if (status === undefined || status === null) {
                return false;
            }
            return status.isCancelable;
        },

        getCancelData(item) {
            if (this.cancelStatus === undefined || this.cancelStatus === null) {
                return {};
            }
            const status = this.cancelStatus[item.id];
            if (status === undefined) {
                return {};
            }
            status.shopwareItemId = item.id;
            status.label = item.label;
            status.payload = item.payload;
            return status;
        },

        updateTrackingPrefilling() {
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

// Template blocks are applied via Component.override so that resolveTokens (same path as
// Component.override plugins like SwagCommercial) is used instead of resolveExtendTokens,
// which does not reliably propagate sub-block overrides inside Vue named slots.
Component.override('mollie-order-line-items-grid', {
    template,
});
