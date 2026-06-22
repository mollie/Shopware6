import template from './mollie-cancel-item.html.twig';
import CancelItemService, { type CancelResponse } from './services/CancelItemService';

const { Component, Mixin } = Shopware;

interface CancelItemComponent {
    cancelService: CancelItemService;
    canceledQuantity: number;
    resetStock: boolean;
    isLoading: boolean;

    [key: string]: any;
}

const componentConfig: ThisType<CancelItemComponent> = {
    template,

    mixins: [Mixin.getByName('notification')],

    inject: ['MolliePaymentsItemCancelService'],

    props: {
        item: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            cancelService: null,
            canceledQuantity: 1,
            resetStock: false,
            isLoading: false,
        };
    },

    created() {
        this.cancelService = new CancelItemService();
    },

    methods: {
        submit() {
            if (this.isLoading) {
                return;
            }
            this.isLoading = true;

            this.MolliePaymentsItemCancelService.cancel(
                this.cancelService.buildCancelRequest(this.item, this.canceledQuantity, this.resetStock),
            )
                .then((response: CancelResponse) => {
                    this.isLoading = false;

                    if (this.cancelService.isCancelSuccess(response)) {
                        this.createNotificationSuccess({
                            message: this.$tc('mollie-payments.modals.cancel.item.success'),
                        });
                    } else {
                        this.createNotificationError({
                            message: this.$tc(this.cancelService.getFailureSnippetKey(response)),
                        });
                    }

                    this.$emit('update-cancel-status', response);
                    this.$emit('close');
                })
                .catch((error: any) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: error.response.data.message,
                    });
                    this.$emit('close');
                });
        },

        close() {
            this.$emit('close');
        },
    },
};

Component.register('mollie-cancel-item', componentConfig);
