const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.extend('mollie-pluginconfig-element-orderstate-select', 'sw-entity-single-select', {
    props: {
        criteria: {
            type: Object,
            required: false,
            default() {
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equals('stateMachine.technicalName', 'order.state'));
                return criteria;
            },
        },
    },
});
