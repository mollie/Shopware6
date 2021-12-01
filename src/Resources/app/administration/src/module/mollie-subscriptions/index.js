import './page/mollie-subscriptions-to-product-list';

const { Module } = Shopware;

Module.register('mollie-subscriptions', {
    type: 'plugin',
    name: 'mollie-subscriptions.title',
    title: 'mollie-subscriptions.title',
    description: '',
    color: '#9AA8B5',
    icon: 'default-basic-shape-star',

    routes: {
        list: {
            component: 'mollie-subscriptions-to-product-list',
            path: 'list',
        },
    },

    navigation: [{
        id: 'mollie-subscriptions',
        label: 'mollie-subscriptions.general.menu.title',
        color: '#A092F0',
        icon: 'default-device-dashboard',
        path: 'mollie.subscriptions.list',
        position: 10,
        parent: 'sw-order',
    }],
});
