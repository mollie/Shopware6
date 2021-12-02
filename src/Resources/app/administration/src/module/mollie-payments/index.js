import './extension/sw-customer';
import './extension/sw-order';
import './components/mollie-test-api-key';

// eslint-disable-next-line no-undef
const { Module } = Shopware;

Module.register('mollie-payments', {
    type: 'plugin',
    name: 'MolliePayments',
    title: 'mollie-payments.general.mainMenuItemGeneral',
    description: 'mollie-payments.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#333',
    icon: 'default-action-settings',
});
