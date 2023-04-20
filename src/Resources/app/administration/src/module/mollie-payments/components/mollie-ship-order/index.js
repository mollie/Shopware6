import template from './mollie-ship-order.html.twig';

// eslint-disable-next-line no-undef
const {Component} = Shopware;

Component.register('mollie-ship-order', {
    template,
    props: {
        shippableLineItems: {
            type: Array,
        },

        order:{
            type: Object,
        },
        tracking: {
            carrier: '',
            code: '',
            url: '',
        },
        showTrackingInfo:false,
    },


    computed: {
        getShipOrderColumns() {
            return [
                {
                    property: 'label',
                    label: this.$tc('mollie-payments.modals.shipping.order.itemHeader'),
                },
                {
                    property: 'quantity',
                    label: this.$tc('mollie-payments.modals.shipping.order.quantityHeader'),
                },
            ];
        },
        shippableLineItems1() {
            return this.orderLineItems
                .filter((item) => this.shippableQuantity(item))
                .map((item) => {
                    return {
                        label: item.label,
                        quantity: this.shippableQuantity(item),
                    }
                });
        },
    },
});