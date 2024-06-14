import template from './mollie-cancel-item.html.twig';

// eslint-disable-next-line no-undef
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
            canceledQuantity: 1,
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
                canceledQuantity: this.canceledQuantity,
                resetStock: this.resetStock,
            }).then((response) => {
                this.isLoading = false;

                if(response.success){
                    this.createNotificationSuccess({
                        message: this.$tc('mollie-payments.modals.cancel.item.success'),
                    });
                }else{
                    this.createNotificationError({
                        message: this.$tc('mollie-payments.modals.cancel.item.failed.'+response.message),
                    });
                }
                this.$emit('update-cancel-status');
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