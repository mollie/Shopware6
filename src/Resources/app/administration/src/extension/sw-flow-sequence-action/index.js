import {ACTION} from '../../constant/mollie-payments.constant'

// eslint-disable-next-line no-undef
const {Component} = Shopware;

Component.override('sw-flow-sequence-action', {
    computed: {

        /**
         *
         * @returns {string|*}
         */
        modalName() {

            if (this.selectedAction === ACTION.MOLLIE_SHIP_ORDER) {
                return 'mollie-payments-flowsequence-action-order-ship-modal';
            }

            if (this.selectedAction === ACTION.MOLLIE_REFUND_ORDER) {
                return 'mollie-payments-flowsequence-action-order-refund-modal';
            }

            return this.$super('modalName');
        },

        /**
         *
         * @returns {{[p: string]: *}}
         */
        actionDescription() {
            const actionDescriptionList = this.$super('actionDescription');

            return {
                ...actionDescriptionList,
                // eslint-disable-next-line no-unused-vars
                [ACTION.MOLLIE_SHIP_ORDER]: (config) => this.$tc('mollie-payments.sw-flow.actions.shipOrder.editor.description'),
                // eslint-disable-next-line no-unused-vars
                [ACTION.MOLLIE_REFUND_ORDER]: (config) => this.$tc('mollie-payments.sw-flow.actions.refundOrder.editor.description'),
            };
        },
    },


    methods: {

        /**
         *
         * @param actionName
         * @returns {*|{icon: string, label: string, value}}
         */
        getActionTitle(actionName) {

            if (actionName === ACTION.MOLLIE_SHIP_ORDER) {
                return {
                    value: actionName,
                    icon: 'default-package-open',
                    label: this.$tc('mollie-payments.sw-flow.actions.shipOrder.editor.title'),
                }
            }

            if (actionName === ACTION.MOLLIE_REFUND_ORDER) {
                return {
                    value: actionName,
                    icon: 'default-symbol-euro',
                    label: this.$tc('mollie-payments.sw-flow.actions.refundOrder.editor.title'),
                }
            }

            return this.$super('getActionTitle', actionName);
        },
    },
});