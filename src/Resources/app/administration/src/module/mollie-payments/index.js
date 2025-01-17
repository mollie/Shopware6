import './acl';
import './extension/sw-order';
import './extension/sw-settings';
import './components/mollie-pluginconfig-element-orderstate-select';
import './components/mollie-pluginconfig-section-info';
import './components/mollie-pluginconfig-section-api';
import './components/mollie-pluginconfig-section-payments';
import './components/mollie-pluginconfig-section-payments-format';
import './components/mollie-pluginconfig-section-rounding';
import './components/mollie-pluginconfig-support-modal';
import './components/mollie-pluginconfig-section-order-lifetime-warning';
import './components/mollie-tracking-info';
import './components/mollie-refund-manager';
import './components/mollie-external-link';
import './components/mollie-internal-link';
import './components/mollie-ship-order';
import './components/mollie-cancel-item';
import './page/mollie-subscriptions-list';
import './page/mollie-subscriptions-detail';

import defaultSearchConfiguration from './default-search-configuration';


// eslint-disable-next-line no-undef
const {Module,Plugin,Service} = Shopware;

// Tell Shopware to wait loading until we call resolve.
const resolve = Plugin.addBootPromise();

/**
 *
 * @type {MolliePaymentsConfigService}
 */
const configService = Service('MolliePaymentsConfigService');

// Because we first have to check if subscription is enabled or not
configService.getSubscriptionConfig().then(result => {
    const navigation = [];

    if (result.enabled === true) {
        navigation.push({
            id: 'mollie-subscriptions',
            label: 'mollie-payments.subscriptions.navigation.title',
            path: 'mollie.payments.subscriptions',
            parent: 'sw-order',
            position: 10,
            privilege: 'mollie_subscription:read',
        });
    }

    Module.register('mollie-payments', {
        type: 'plugin',
        title: 'mollie-payments.general.mainMenuItemGeneral',
        description: 'mollie-payments.general.descriptionTextModule',
        version: '1.0.0',
        targetVersion: '1.0.0',
        color: '#333',
        icon: 'regular-shopping-bag',
        entity: 'mollie_subscription',

        routes: {
            subscriptions: {
                component: 'mollie-subscriptions-list',
                path: 'subscriptions',
                meta: {
                    privilege: 'mollie_subscription:read',
                },
            },

            subscription_detail: {
                component: 'mollie-subscriptions-detail',
                path: 'subscription/detail/:id',
                props: {
                    default: ($route) => {
                        return {
                            subscriptionId: $route.params.id,
                        };
                    },
                },
                meta: {
                    parentPath: 'mollie.payments.subscriptions',
                    privilege: 'mollie_subscription:read',
                },
            },
        },

        navigation,

        defaultSearchConfiguration,
    });

}).finally(()=>{
    // Now tell Shopware it's okay to load the administration
    resolve();
});
