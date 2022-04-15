import './extension/sw-customer';
import './extension/sw-order';
import './components/mollie-pluginconfig-section-info';
import './components/mollie-pluginconfig-section-api';
import './components/mollie-pluginconfig-section-payments';
import './components/mollie-tracking-info';
import './components/mollie-refund-manager';
import './page/mollie-subscriptions-list';


// eslint-disable-next-line no-undef
const {Module} = Shopware;

Module.register('mollie-payments', {
    type: 'plugin',
    name: 'mollie-payments.pluginTitle',
    title: 'mollie-payments.general.mainMenuItemGeneral',
    description: 'mollie-payments.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#333',
    icon: 'default-action-settings',

    routes: {
        subscriptions: {
            component: 'mollie-subscriptions-list',
            path: 'subscriptions',
        },
    },

    navigation: [
        {
            id: 'mollie-subscriptions',
            label: 'mollie-payments.subscriptions.navigation.title',
            privilege: 'order.viewer',
            path: 'mollie.payments.subscriptions',
            parent: 'sw-order',
            position: 10,
        },
    ],

});
