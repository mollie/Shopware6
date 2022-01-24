import template from './action-order-ship-modal.twig'
import './action-order-ship-modal.scss'

// eslint-disable-next-line no-undef
const {Component} = Shopware;

Component.register('mollie-payments-flowsequence-action-order-ship-modal', {
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
            warnings: [],
        };
    },

    created() {
        this.createdComponent();

        this.MolliePaymentsConfigService.validateFlowBuilder().then((response) => {
            this.warnings = response.actions.shipping.warnings;
        });
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