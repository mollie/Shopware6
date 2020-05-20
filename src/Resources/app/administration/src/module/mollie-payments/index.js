import './extension/sw-order';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

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
        'en-GB': enGB
    }
});
