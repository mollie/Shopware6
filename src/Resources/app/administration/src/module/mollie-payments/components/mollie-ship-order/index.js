import template from './mollie-ship-order.html.twig';
import './mollie-ship-order.scss';
import MollieShippingEvents from './MollieShippingEvents';
import MollieShipping from './MollieShipping';

// eslint-disable-next-line no-undef
const {Component, Mixin} = Shopware;

Component.register('mollie-ship-order', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    inject: [
        'MolliePaymentsShippingService',
        'MolliePaymentsConfigService',
        'acl',
    ],

    props: {
        order: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            shippableLineItems: [],
            shippedLineItems: [],
            showTrackingInfo: false,
            tracking: {
                carrier: '',
                code: '',
                url: '',
            },
        };
    },


    /**
     *
     */
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

        /**
         *
         */
        async createdComponent() {

            this.showTrackingInfo = false;

            this.tracking = {
                carrier: '',
                code: '',
                url: '',
            };


            // load the already shipped items
            // so that we can calculate what is left to be shipped
            await this.MolliePaymentsShippingService
                .status({
                    orderId: this.order.id,
                })
                .then((response) => {
                    this.shippedLineItems = response;
                });


            const shipping = new MollieShipping(this.MolliePaymentsShippingService);

            shipping.getShippableItems(this.order).then((items) => {

                // this is required to make sure the "select all" works
                // because we need to have a default value
                for (let i = 0; i < items.length; i++) {
                    const item = items[i];
                    item.selected = false;
                    item.originalQuantity = item.quantity;
                }

                this.shippableLineItems = items;
            });

            // if we have at least 1 tracking code in the order
            // then try to prefill our tracking information
            // also automatically enable the tracking data (it can be turned off again by the merchant)
            if (this.order.deliveries.length) {
                const delivery = this.order.deliveries.first();
                this.showTrackingInfo = (delivery.trackingCodes.length >= 1);
            }
        },

        /**
         *
         */
        btnSelectAllItems_Click() {
            for (let i = 0; i < this.shippableLineItems.length; i++) {
                const item = this.shippableLineItems[i];
                if (item.originalQuantity > 0) {
                    item.selected = true;
                }
            }
        },

        /**
         *
         */
        btnResetItems_Click() {
            for (let i = 0; i < this.shippableLineItems.length; i++) {
                const item = this.shippableLineItems[i];
                item.selected = false;
                item.quantity = item.originalQuantity;
            }
        },

        /**
         *
         */
        onShipOrder() {

            var shippingItems = [];

            for (let i = 0; i < this.shippableLineItems.length; i++) {
                const item = this.shippableLineItems[i];

                if (item.selected) {
                    shippingItems.push({
                        'id': item.id,
                        'quantity': item.quantity,
                    })
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

                    // send global event
                    this.$root.$emit(MollieShippingEvents.EventShippedOrder);

                    this.createNotificationSuccess({
                        message: this.$tc('mollie-payments.modals.shipping.item.success'),
                    });
                })
                .catch((response) => {
                    const msg = (response.response.data.message) ? response.response.data.message : response.response.data.errors[0];
                    this.createNotificationError({
                        message: msg,
                    });
                });
        },

    },

});
