import template from './action-order-refund-modal.twig'
import './action-order-refund-modal.scss'

// eslint-disable-next-line no-undef
const {Component} = Shopware;

Component.register('mollie-payments-flowsequence-action-order-refund-modal', {
    template,

    inject: [
        'MolliePaymentsConfigService',
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            tags: [],
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.sequence && this.sequence.config) {
                this.tags = this.sequence.config.tags;
            } else {
                this.tags = [];
            }
        },

        onClose() {
            this.$emit('modal-close');
        },

        onAddAction() {
            const sequence = {
                ...this.sequence,
                config: {
                    ...this.config,
                    tags: this.tags,
                },
            };

            this.$emit('process-finish', sequence);
        },
    },
});