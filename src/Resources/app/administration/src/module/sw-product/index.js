import './page/sw-product-detail'
import './view/sw-product-detail-mollie'

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import nlNL from './snippet/nl-NL.json'


// eslint-disable-next-line no-undef
const {Module} = Shopware;

Module.register('mollie-sw-product-detail', {
    type: 'plugin',
    name: 'MolliePayments',
    title: 'Mollie',
    description: 'Mollie Module',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#333',
    icon: 'default-action-settings',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
        'nl-NL': nlNL,
    },

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
