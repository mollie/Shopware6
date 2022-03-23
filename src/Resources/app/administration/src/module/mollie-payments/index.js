import './extension/sw-customer';
import './extension/sw-order';
import './components/mollie-pluginconfig-section-info';
import './components/mollie-pluginconfig-section-api';
import './components/mollie-pluginconfig-section-payments';
import './components/mollie-tracking-info';
import './components/mollie-refund-manager';

// eslint-disable-next-line no-undef
const { Module } = Shopware;

Module.register('mollie-payments', {
    type: 'plugin',
    name: 'mollie-payments.pluginTitle',
    title: 'mollie-payments.general.mainMenuItemGeneral',
    description: 'mollie-payments.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#333',
    icon: 'default-action-settings',
});
