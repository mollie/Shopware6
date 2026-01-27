import './page/sw-customer-detail';
import './view/sw-customer-mollie-subscriptions';

// eslint-disable-next-line no-undef
const { Module } = Shopware;

Module.register('mollie-sw-customer-detail', {
    type: 'plugin',
    name: 'customer-route',
    title: 'mollie-payments.pluginTitle',
    description: 'mollie-payments.pluginDescription',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#333',
    icon: 'default-action-settings',

    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.customer.detail') {
            currentRoute.children.push({
                name: 'sw.customer.detail.mollie-subscriptions',
                path: '/sw/customer/detail/:id/mollie-subscriptions',
                component: 'sw-customer-mollie-subscriptions',
                meta: {
                    parentPath: 'sw.customer.index',
                },
            });
        }
        next(currentRoute);
    },
});
