import template from './mollie-ship-order.html.twig';
import './mollie-ship-order.scss';
import MollieShippingEvents from './MollieShippingEvents';
import ShippableItemsService from './services/ShippableItemsService';

const { Component, Mixin } = Shopware;

interface ShipOrderComponent {
    shippableItemsService: ShippableItemsService;
    shippableLineItems: any[];
    showTrackingInfo: boolean;
    tracking: { carrier: string; code: string; url: string };

    [key: string]: any;
}

const componentConfig: ThisType<ShipOrderComponent> = {
    template,

    mixins: [Mixin.getByName('notification')],

    inject: ['MolliePaymentsShippingService', 'acl'],

    props: {
        order: {
            type: Object,
            required: true,
        },
        shippingStatus: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            shippableItemsService: null,
            shippableLineItems: [],
            showTrackingInfo: false,
            tracking: {
                carrier: '',
                code: '',
                url: '',
            },
        };
    },

    computed: {
        getShipOrderColumns() {
            return [
                {
                    property: 'itemselect',
                    label: '',
                },
                {
                    property: 'label',
                    label: this.$tc('mollie-payments.modals.shipping.order.itemHeader'),
                },
                {
                    property: 'quantity',
                    label: this.$tc('mollie-payments.modals.shipping.order.quantityHeader'),
                    width: '160px',
                },
                {
                    property: 'originalQuantity',
                    label: this.$tc('mollie-payments.modals.shipping.order.originalQuantityHeader'),
                    width: '160px',
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.shippableItemsService = new ShippableItemsService();

            this.showTrackingInfo = false;
            this.tracking = { carrier: '', code: '', url: '' };

            this.shippableLineItems = this.shippableItemsService.buildShippableLineItems(
                this.order.lineItems,
                this.shippingStatus,
            );

            if (this.order.deliveries.length) {
                const delivery = this.order.deliveries.first();
                this.showTrackingInfo = delivery.trackingCodes.length >= 1;
            }
        },

        btnSelectAllItems_Click() {
            this.shippableLineItems.forEach((item: any) => {
                if (item.originalQuantity > 0) {
                    item.selected = true;
                }
            });
        },

        btnResetItems_Click() {
            this.shippableLineItems.forEach((item: any) => {
                item.selected = false;
                item.quantity = item.originalQuantity;
            });
        },

        onShipOrder() {
            const shippingItems = this.shippableItemsService.collectSelectedItems(this.shippableLineItems);

            this.MolliePaymentsShippingService.shipOrder(
                this.order.id,
                this.tracking.carrier,
                this.tracking.code,
                this.tracking.url,
                shippingItems,
            )
                .then(() => {
                    if (Shopware.Utils && Shopware.Utils.EventBus) {
                        Shopware.Utils.EventBus.emit(MollieShippingEvents.EventShippedOrder);
                    } else {
                        this.$root.$emit(MollieShippingEvents.EventShippedOrder);
                    }

                    this.createNotificationSuccess({
                        message: this.$tc('mollie-payments.modals.shipping.item.success'),
                    });
                })
                .catch((response: any) => {
                    const msg = response.response.data.message
                        ? response.response.data.message
                        : response.response.data.errors[0];
                    this.createNotificationError({
                        message: msg,
                    });
                });
        },
    },
};

Component.register('mollie-ship-order', componentConfig);
