import template from './action-order-ship-modal.twig';
import './action-order-ship-modal.scss';
import createFlowActionModalConfig from '../flowActionModalConfig';

const { Component } = Shopware;

interface ShipModalAction {
    tags: any[];
    warnings: any[];

    [key: string]: any;
}

// The ship modal reuses the shared flow-action config but also loads the
// shipping warnings from the config validation endpoint.
const componentConfig: ThisType<ShipModalAction> = {
    ...createFlowActionModalConfig(template),

    data() {
        return {
            tags: [],
            warnings: [],
        };
    },

    created() {
        this.createdComponent();

        this.MolliePaymentsConfigService.validateFlowBuilder().then((response: any) => {
            this.warnings = response.actions.shipping.warnings;
        });
    },
};

Component.register('mollie-payments-flowsequence-action-order-ship-modal', componentConfig);
