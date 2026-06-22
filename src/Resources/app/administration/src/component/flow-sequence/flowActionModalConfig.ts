interface FlowActionModal {
    tags: any[];

    [key: string]: any;
}

/**
 * Shared config for the Mollie flow-sequence action modals (ship / refund),
 * which share props, tag handling and the add/close handlers. The ship modal
 * additionally overrides data()/created() to load its warnings.
 */
export default function createFlowActionModalConfig(template: any) {
    const config: ThisType<FlowActionModal> = {
        template,

        inject: ['MolliePaymentsConfigService'],

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
    };

    return config;
}
