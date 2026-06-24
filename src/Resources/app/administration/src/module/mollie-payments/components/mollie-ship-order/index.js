import template from './mollie-ship-order.html.twig';
import './mollie-ship-order.scss';
import MollieShippingEvents from './MollieShippingEvents';

// eslint-disable-next-line no-undef
const { Component, Mixin } = Shopware;

Component.register('mollie-ship-order', {
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
            shippableLineItems: [],
            showTrackingInfo: false,
            tracking: {
                carrier: '',
                code: '',
                url: '',
            },
        };
    },

    created() {
        this.createdComponent();
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

    methods: {
        createdComponent() {
            this.showTrackingInfo = false;
            this.tracking = { carrier: '', code: '', url: '' };

            const items = [];
            for (let i = 0; i < this.order.lineItems.length; i++) {
                const lineItem = this.order.lineItems[i];
                const status = this.shippingStatus[lineItem.id];
                const shippableQty = status ? (status.shippableQuantity ?? 0) : 0;

                items.push({
                    id: lineItem.id,
                    mollieId: status ? status.mollieId : null,
                    label: lineItem.label,
                    quantity: shippableQty,
                    originalQuantity: shippableQty,
                    selected: false,
                });
            }
            this.shippableLineItems = items;

            if (this.order.deliveries.length) {
                const delivery = this.order.deliveries.first();
                this.showTrackingInfo = delivery.trackingCodes.length >= 1;
            }
        },

        btnSelectAllItems_Click() {
            for (let i = 0; i < this.shippableLineItems.length; i++) {
                const item = this.shippableLineItems[i];
                if (item.originalQuantity > 0) {
                    item.selected = true;
                }
            }
        },

        btnResetItems_Click() {
            for (let i = 0; i < this.shippableLineItems.length; i++) {
                const item = this.shippableLineItems[i];
                item.selected = false;
                item.quantity = item.originalQuantity;
            }
        },

        onShipOrder() {
            var shippingItems = [];

            for (let i = 0; i < this.shippableLineItems.length; i++) {
                const item = this.shippableLineItems[i];
                if (item.selected) {
                    shippingItems.push({
                        id: item.id,
                        quantity: item.quantity,
                    });
                }
            }

            this.MolliePaymentsShippingService.shipOrder(
                this.order.id,
                this.tracking.carrier,
                this.tracking.code,
                this.tracking.url,
                shippingItems,
            )
                .then(() => {
                    // eslint-disable-next-line no-undef
                    if (Shopware.Utils && Shopware.Utils.EventBus) {
                        // eslint-disable-next-line no-undef
                        Shopware.Utils.EventBus.emit(MollieShippingEvents.EventShippedOrder);
                    } else {
                        this.$root.$emit(MollieShippingEvents.EventShippedOrder);
                    }

                    this.createNotificationSuccess({
                        message: this.$tc('mollie-payments.modals.shipping.item.success'),
                    });
                })
                .catch((response) => {
                    const msg = response.response.data.message
                        ? response.response.data.message
                        : response.response.data.errors[0];
                    this.createNotificationError({
                        message: msg,
                    });
                });
        },
    },
});
