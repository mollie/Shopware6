import template from './mollie-ship-order.html.twig';
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
                    property: 'label',
                    label: this.$tc('mollie-payments.modals.shipping.order.itemHeader'),
                }, {
                    property: 'quantity',
                    label: this.$tc('mollie-payments.modals.shipping.order.quantityHeader'),
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
        onShipOrder() {

            const params = {
                orderId: this.order.id,
                trackingCarrier: this.tracking.carrier,
                trackingCode: this.tracking.code,
                trackingUrl: this.tracking.url,
            };

            this.MolliePaymentsShippingService.shipOrder(params)
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
