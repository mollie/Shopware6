import { ACTION } from '../../constant/mollie-payments.constant';

const { Component } = Shopware;

interface FlowSequenceActionOverride {
    [key: string]: any;
}

const overrideConfig: ThisType<FlowSequenceActionOverride> = {
    computed: {
        modalName() {
            if (this.selectedAction === ACTION.MOLLIE_SHIP_ORDER) {
                return 'mollie-payments-flowsequence-action-order-ship-modal';
            }

            if (this.selectedAction === ACTION.MOLLIE_REFUND_ORDER) {
                return 'mollie-payments-flowsequence-action-order-refund-modal';
            }

            return this.$super('modalName');
        },

        actionDescription() {
            const actionDescriptionList = this.$super('actionDescription');

            return {
                ...actionDescriptionList,
                [ACTION.MOLLIE_SHIP_ORDER]: () =>
                    this.$tc('mollie-payments.sw-flow.actions.shipOrder.editor.description'),
                [ACTION.MOLLIE_REFUND_ORDER]: () =>
                    this.$tc('mollie-payments.sw-flow.actions.refundOrder.editor.description'),
            };
        },
    },

    methods: {
        getActionTitle(actionName: string) {
            if (actionName === ACTION.MOLLIE_SHIP_ORDER) {
                return {
                    value: actionName,
                    icon: 'default-package-open',
                    label: this.$tc('mollie-payments.sw-flow.actions.shipOrder.editor.title'),
                };
            }

            if (actionName === ACTION.MOLLIE_REFUND_ORDER) {
                return {
                    value: actionName,
                    icon: 'default-symbol-euro',
                    label: this.$tc('mollie-payments.sw-flow.actions.refundOrder.editor.title'),
                };
            }

            return this.$super('getActionTitle', actionName);
        },
    },
};

Component.override('sw-flow-sequence-action', overrideConfig);
