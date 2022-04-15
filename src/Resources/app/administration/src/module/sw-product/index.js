import './page/sw-product-detail'
import './view/sw-product-detail-mollie'


// eslint-disable-next-line no-undef
const {Module} = Shopware;

Module.register('mollie-sw-product-detail', {
    type: 'plugin',
    name: 'MolliePayments',
    title: 'mollie-payments.pluginTitle',
    description: 'mollie-payments.pluginDescription',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#333',
    icon: 'default-action-settings',

    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.product.detail') {
            currentRoute.children.push({
                name: 'sw.product.detail.mollie',
                path: '/sw/product/detail/:id/mollie',
                component: 'sw-product-detail-mollie',
                meta: {
                    parentPath: 'sw.product.index',
                },
            });
        }
        next(currentRoute);
    },
});
