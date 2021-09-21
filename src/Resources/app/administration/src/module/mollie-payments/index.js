import './extension/sw-customer';
import './extension/sw-order';
import './components/mollie-test-api-key';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import nlNL from './snippet/nl-NL.json'

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

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
        'nl-NL': nlNL,
    },
});
