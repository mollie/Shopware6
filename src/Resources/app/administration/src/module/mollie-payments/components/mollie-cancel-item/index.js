import template from "./mollie-cancel-item.html.twig";

const {Component, Mixin} = Shopware;

Component.register('mollie-cancel-item', {
    template,
    props: {
        item: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            cancelableQuantity: 0,
            canceled: 0,
            resetStock: false,
            isLoading: false,
        };
    },
    mixins: [
        Mixin.getByName('notification'),
    ],

    inject: [
        'MolliePaymentsItemCancelService',
    ],

    methods: {
        submit() {
            this.isLoading = true;

            this.MolliePaymentsItemCancelService.cancel({
                mollieOrderId: this.item.mollieOrderId,
                mollieLineId: this.item.mollieId,
                shopwareItemId: this.item.shopwareItemId,
                quantityCanceled: this.canceled,
                resetStock: this.resetStock,
            }).then((response) => {
                this.isLoading = false;
                this.createNotificationSuccess({
                    message: this.$tc('mollie-payments.modals.shipping.item.success'),
                });
                this.$emit('close');
            }).catch(error => {
                this.isLoading = false;
                this.createNotificationError({
                    message: error.response.data.message,
                });
                this.$emit('close');
            })
        },
        close() {
            this.$emit('close');
        },
    },
})