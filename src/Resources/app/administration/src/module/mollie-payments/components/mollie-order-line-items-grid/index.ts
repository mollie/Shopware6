import template from './mollie-order-line-items-grid.html.twig';
import OrderAttributes from '../../../../core/models/OrderAttributes';
import MollieShippingEvents from '../mollie-ship-order/MollieShippingEvents';
import LineItemStatusService, { type CancelResponse } from './services/LineItemStatusService';

const { Component, Mixin } = Shopware;
const { string: stringUtils } = Shopware.Utils;

interface LineItemsGridComponent {
    statusService: LineItemStatusService;
    shippingStatus: Record<string, any> | null;
    cancelStatus: Record<string, any> | null;
    isShipItemLoading: boolean;
    shipQuantity: number;
    showTrackingInfo: boolean;
    tracking: { carrier: string; code: string; url: string };

    [key: string]: any;
}

const componentConfig: ThisType<LineItemsGridComponent> = {
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
            statusService: null,
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
            return new OrderAttributes(this.order).isMollieOrder();
        },

        mollieId() {
            return new OrderAttributes(this.order).getMollieID();
        },
    },

    watch: {
        initialCancelStatus(value: Record<string, any> | null) {
            if (value !== null && value !== undefined) {
                this.cancelStatus = value;
            }
        },
    },

    created() {
        this.statusService = new LineItemStatusService();

        if (!this.isMollieOrder) {
            return;
        }

        if (this.$root && this.$root.$on) {
            this.$root.$on(MollieShippingEvents.EventShippedOrder, () => this.onCloseShipItemModal());
        } else {
            Shopware.Utils.EventBus.on(MollieShippingEvents.EventShippedOrder, () => this.onCloseShipItemModal());
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
            this.shippingStatus = await this.MolliePaymentsShippingService.status({ orderId: this.order.id });
        },

        loadMollieCancelStatus(cancelResponse: CancelResponse) {
            this.cancelStatus = this.statusService.applyCancelResponse(this.cancelStatus, cancelResponse);
        },

        onOpenShipItemModal(item: any) {
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

        onOpenCancelItemModal(item: any) {
            this.cancelItemModal = item.id;
        },

        closeCancelItemModal() {
            this.cancelItemModal = null;
        },

        onConfirmShipItem(item: any) {
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
                .then(() => {
                    this.isShipItemLoading = false;
                    this.createNotificationSuccess({
                        message: this.$tc('mollie-payments.modals.shipping.item.success'),
                    });
                    this.onCloseShipItemModal();

                    if (this.$root && this.$root.$emit) {
                        this.$root.$emit(MollieShippingEvents.EventShippedOrder);
                    } else {
                        Shopware.Utils.EventBus.emit(MollieShippingEvents.EventShippedOrder);
                    }
                })
                .then(() => {
                    this.$emit('ship-item-success');
                })
                .catch((response: any) => {
                    this.isShipItemLoading = false;
                    this.createNotificationError({
                        message: response.response.data.message,
                    });
                });
        },

        setMaxQuantity(item: any) {
            this.shipQuantity = this.shippableQuantity(item);
        },

        isShippable(item: any) {
            return this.shippableQuantity(item) > 0;
        },

        shippableQuantity(item: any) {
            return this.statusService.shippableQuantity(this.shippingStatus, item.id);
        },

        shippedQuantity(item: any) {
            return this.statusService.shippedQuantity(this.shippingStatus, item.id);
        },

        canceledQuantity(item: any) {
            return this.statusService.canceledQuantity(this.cancelStatus, item.id);
        },

        isCancelable(item: any) {
            return this.statusService.isCancelable(this.cancelStatus, item.id);
        },

        getCancelData(item: any) {
            return this.statusService.buildCancelData(this.cancelStatus, item);
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
            return (
                !stringUtils.isEmptyOrSpaces(this.tracking.carrier) && !stringUtils.isEmptyOrSpaces(this.tracking.code)
            );
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
};

Component.extend('mollie-order-line-items-grid', 'sw-order-line-items-grid', componentConfig);

// Template blocks are applied via Component.override so that resolveTokens (same path as
// Component.override plugins like SwagCommercial) is used instead of resolveExtendTokens,
// which does not reliably propagate sub-block overrides inside Vue named slots.
Component.override('mollie-order-line-items-grid', {
    template,
});
